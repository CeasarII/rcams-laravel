<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceLiquidation;
use App\Models\DocumentType;
use App\Models\LiquidationItem;
use App\Models\LiquidationItemAttachment;
use App\Models\LiquidationItemStatus;
use App\Models\NatureOfCost;
use App\Support\PhVat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Expense breakdown ("Provide expense breakdown" / Edit / Revise liquidation).
 * Only the Requestor, only while the liquidation is Draft or sent back —
 * enforced by CashAdvanceLiquidationPolicy::update().
 *
 * VAT is never taken from the form: the client sends the VAT-inclusive gross
 * amount and the VAT nature; net and input tax are computed here (PhVat).
 */
class LiquidationItemController extends Controller
{
    private const DISK = 'local';

    public function create(CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('update', $liquidation);

        return view('liquidations.items.form', $this->formData($liquidation, null));
    }

    public function store(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('update', $liquidation);

        $data = $request->validate($this->rules($request), $this->fileMessages());

        $item = $liquidation->items()->create($this->itemData($data) + [
            'liquidation_item_status_id' => LiquidationItemStatus::where('code', LiquidationItemStatus::EXPENSE)->firstOrFail()->id,
        ]);

        $this->storeFiles($item, $request, $data);
        $liquidation->recalculateTotals();

        return redirect()->route('liquidations.show', $liquidation)->with('status', 'Item added.');
    }

    public function edit(CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('update', $liquidation);
        $this->assertChangeable($liquidation, $item);

        return view('liquidations.items.form', $this->formData($liquidation, $item));
    }

    public function update(Request $request, CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('update', $liquidation);
        $this->assertChangeable($liquidation, $item);

        $data = $request->validate($this->rules($request), $this->fileMessages());

        $item->update($this->itemData($data));
        $this->storeFiles($item, $request, $data);
        $liquidation->recalculateTotals();

        return redirect()->route('liquidations.show', $liquidation)->with('status', 'Item updated.');
    }

    public function destroy(CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('update', $liquidation);
        $this->assertChangeable($liquidation, $item);

        foreach ($item->attachments as $attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
        }
        $item->delete(); // attachment rows cascade
        $liquidation->recalculateTotals();

        return back()->with('status', 'Item removed.');
    }

    // ---- attachments (soft copies of OR / invoice / receipts) ---------

    public function storeAttachments(Request $request, CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('update', $liquidation);
        $this->assertBelongs($liquidation, $item);

        $data = $request->validate($this->fileRules(required: true), $this->fileMessages());
        $count = $this->storeFiles($item, $request, $data);

        return back()->with('status', $count === 1 ? 'File attached.' : "{$count} files attached.");
    }

    public function downloadAttachment(CashAdvanceLiquidation $liquidation, LiquidationItem $item, LiquidationItemAttachment $attachment)
    {
        $this->authorize('view', $liquidation);
        $this->assertBelongs($liquidation, $item);
        abort_unless($attachment->liquidation_item_id === $item->id, 404);
        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404, 'File not found on storage.');

        return Storage::disk(self::DISK)->download($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(CashAdvanceLiquidation $liquidation, LiquidationItem $item, LiquidationItemAttachment $attachment)
    {
        $this->authorize('update', $liquidation);
        $this->assertBelongs($liquidation, $item);
        abort_unless($attachment->liquidation_item_id === $item->id, 404);

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    // ---- internals ----------------------------------------------------

    private function rules(Request $request): array
    {
        return [
            'item_date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'supplier_name' => ['required', 'string', 'max:150'],
            // A VAT invoice must carry the supplier's TIN; Non-VAT receipts may omit it.
            'tin' => [
                Rule::requiredIf($request->input('vat_nature') === PhVat::VAT),
                'nullable', 'string', 'max:20', 'regex:'.PhVat::TIN_PATTERN,
            ],
            'nature_of_cost_id' => ['required', 'exists:nature_of_costs,id'],
            'vat_nature' => ['required', Rule::in([PhVat::VAT, PhVat::NON_VAT])],
            'gross_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ] + $this->fileRules(required: false);
    }

    private function fileRules(bool $required): array
    {
        return [
            'document_type_id' => [$required ? 'required' : 'required_with:files', 'nullable', 'exists:document_types,id'],
            'files' => [$required ? 'required' : 'nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    private function fileMessages(): array
    {
        return [
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Allowed types: PDF, JPG, PNG.',
            'files.*.uploaded' => 'Upload failed — the file may exceed the server upload limit (php.ini upload_max_filesize).',
        ];
    }

    private function itemData(array $data): array
    {
        $split = PhVat::split($data['gross_amount'], $data['vat_nature']);

        return [
            'item_date' => $data['item_date'],
            'purpose' => $data['purpose'],
            'reference' => $data['reference'] ?? null,
            'supplier_name' => $data['supplier_name'],
            'tin' => $data['tin'] ?? null,
            'nature_of_cost_id' => $data['nature_of_cost_id'],
            'vat_nature' => $data['vat_nature'],
            'vat_rate' => $split['rate'],
            'gross_amount' => number_format((float) $data['gross_amount'], 2, '.', ''),
            'net_amount' => $split['net'],
            'input_tax' => $split['input_tax'],
        ];
    }

    private function storeFiles(LiquidationItem $item, Request $request, array $data): int
    {
        $files = $request->file('files') ?? [];

        foreach ($files as $file) {
            $storedName = Str::uuid().'.'.$file->extension();
            $path = $file->storeAs("attachments/liquidations/{$item->liquidation_id}/items/{$item->id}", $storedName, self::DISK);

            $item->attachments()->create([
                'document_type_id' => $data['document_type_id'],
                'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }

        return count($files);
    }

    private function formData(CashAdvanceLiquidation $liquidation, ?LiquidationItem $item): array
    {
        return [
            'liquidation' => $liquidation,
            'item' => $item?->load('attachments.documentType'),
            // Active entries, plus the one already on the item if it was deactivated since.
            'natures' => NatureOfCost::query()->where('is_active', true)
                ->when($item, fn ($q) => $q->orWhere('id', $item->nature_of_cost_id))->orderBy('label')->get(),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('label')->get(),
            'vatRate' => PhVat::rate(),
        ];
    }

    private function assertBelongs(CashAdvanceLiquidation $liquidation, LiquidationItem $item): void
    {
        abort_unless($item->liquidation_id === $liquidation->id, 404);
    }

    /** Items under a Questionable Expense belong to its own process and can't be edited or removed. */
    private function assertChangeable(CashAdvanceLiquidation $liquidation, LiquidationItem $item): void
    {
        $this->assertBelongs($liquidation, $item);
        abort_if($item->questionableExpense()->exists(), 403, 'This item is under a Questionable Expense and can no longer be changed.');
    }
}
