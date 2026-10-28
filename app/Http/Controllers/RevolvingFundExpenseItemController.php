<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesExpenseItemInput;
use App\Models\Attachment;
use App\Models\LiquidationItemStatus;
use App\Models\NatureOfCost;
use App\Models\QuestionableExpenseStatus;
use App\Models\RevolvingFundExpense;
use App\Models\RevolvingFundExpenseItem;
use App\Support\PhVat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Voyage expense lines. Editable by the Requestor while Draft/Returned and by
 * the OE when Accounting sent it back to them — RevolvingFundExpensePolicy::update().
 *
 * "Revise expense details and tag": editing or removing a line that was tagged
 * questionable clears its tag; whoever reviews next flags it again if it is
 * still questionable.
 */
class RevolvingFundExpenseItemController extends Controller
{
    use HandlesExpenseItemInput;

    private const DISK = 'local';

    public function create(RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('update', $revolvingFundExpense);

        return view('revolving-fund-expenses.items.form', $this->formData($revolvingFundExpense, null));
    }

    public function store(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('update', $revolvingFundExpense);

        $data = $request->validate($this->itemRules($request), $this->fileMessages());

        $item = $revolvingFundExpense->items()->create($this->itemData($data) + [
            'liquidation_item_status_id' => $this->itemStatusId(LiquidationItemStatus::EXPENSE),
        ]);

        $this->storeFiles($item, $request);
        $revolvingFundExpense->recalculateTotal();

        return redirect()->route('revolving-fund-expenses.show', $revolvingFundExpense)->with('status', 'Item added.');
    }

    public function edit(RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('update', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);

        return view('revolving-fund-expenses.items.form', $this->formData($revolvingFundExpense, $item));
    }

    public function update(Request $request, RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('update', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);

        $data = $request->validate($this->itemRules($request), $this->fileMessages());

        $item->update($this->itemData($data));
        $this->clearTag($item);
        $this->storeFiles($item, $request);
        $revolvingFundExpense->recalculateTotal();

        return redirect()->route('revolving-fund-expenses.show', $revolvingFundExpense)->with('status', 'Item updated.');
    }

    public function destroy(RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('update', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);

        $this->clearTag($item);

        foreach ($item->attachments as $attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
            $attachment->delete();
        }
        $item->delete();
        $revolvingFundExpense->recalculateTotal();

        return back()->with('status', 'Item removed.');
    }

    // ---- soft copies --------------------------------------------------

    public function storeAttachments(Request $request, RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('update', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], $this->fileMessages());

        $count = $this->storeFiles($item, $request);

        return back()->with('status', $count === 1 ? 'File attached.' : "{$count} files attached.");
    }

    public function downloadAttachment(RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item, Attachment $attachment)
    {
        $this->authorize('view', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);
        $this->assertOwnsAttachment($item, $attachment);
        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404, 'File not found on storage.');

        return Storage::disk(self::DISK)->download($attachment->file_path, $attachment->original_file_name);
    }

    public function destroyAttachment(RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item, Attachment $attachment)
    {
        $this->authorize('update', $revolvingFundExpense);
        $this->assertBelongs($revolvingFundExpense, $item);
        $this->assertOwnsAttachment($item, $attachment);

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    // ---- internals ----------------------------------------------------

    private function storeFiles(RevolvingFundExpenseItem $item, Request $request): int
    {
        $files = $request->file('files') ?? [];

        foreach ($files as $file) {
            $storedName = Str::uuid().'.'.$file->extension();
            $path = $file->storeAs("attachments/revolving-fund-expenses/{$item->expense_id}/items/{$item->id}", $storedName, self::DISK);

            $item->attachments()->create([
                'original_file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'stored_file_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }

        return count($files);
    }

    /** A line being revised loses its questionable tag (only a not-yet-HC-pending tag can exist while editable). */
    private function clearTag(RevolvingFundExpenseItem $item): void
    {
        $qe = $item->questionableExpense()->with('status')->first();

        if (! $qe) {
            return;
        }

        abort_unless($qe->status->code === QuestionableExpenseStatus::TAGGED, 403, 'This item is under a Questionable Expense and can no longer be changed.');

        $qe->delete(); // its events cascade
        $item->update(['liquidation_item_status_id' => $this->itemStatusId(LiquidationItemStatus::EXPENSE)]);
    }

    private function itemStatusId(string $code): int
    {
        return LiquidationItemStatus::where('code', $code)->firstOrFail()->id;
    }

    private function formData(RevolvingFundExpense $expense, ?RevolvingFundExpenseItem $item): array
    {
        return [
            'expense' => $expense->load('voyage.revolvingFund'),
            'item' => $item?->load('attachments'),
            'natures' => NatureOfCost::query()->where('is_active', true)
                ->when($item, fn ($q) => $q->orWhere('id', $item->nature_of_cost_id))->orderBy('label')->get(),
            'vatRate' => PhVat::rate(),
        ];
    }

    private function assertBelongs(RevolvingFundExpense $expense, RevolvingFundExpenseItem $item): void
    {
        abort_unless($item->expense_id === $expense->id, 404);
    }

    private function assertOwnsAttachment(RevolvingFundExpenseItem $item, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === $item->getMorphClass() && (int) $attachment->attachable_id === $item->id,
            404
        );
    }
}
