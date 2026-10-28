<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\QuestionableExpense;
use App\Models\QuestionableExpenseStatus;
use App\Models\User;
use App\Services\QuestionableExpenseWorkflowService;
use App\Support\LiquidationAccess;
use App\Support\QueueFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionableExpenseController extends Controller
{
    private const DISK = 'local';

    public function __construct(private QuestionableExpenseWorkflowService $workflow)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = LiquidationAccess::roles($user);
        $filters = $this->filtersFor($user, $roles);
        $key = QueueFilters::resolve($filters, $request->query('filter'));

        $visible = fn () => LiquidationAccess::scopeQuestionableExpenses(QuestionableExpense::query(), $user, $roles);

        $search = trim((string) $request->query('q', ''));

        $query = $visible()->with([
            'status', 'item.liquidation.preparer', 'item.natureOfCost',
            'revolvingFundExpenseItem.expense.preparer', 'revolvingFundExpenseItem.natureOfCost',
        ])->latest();
        $filters[$key]['apply']($query);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($i) => $i->where('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('liquidation', fn ($l) => $l->where('control_no', 'like', "%{$search}%")))
                    ->orWhereHas('revolvingFundExpenseItem', fn ($i) => $i->where('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('expense', fn ($e) => $e->where('control_no', 'like', "%{$search}%")));
            });
        }

        return view('questionable-expenses.index', [
            'questionableExpenses' => $query->paginate(20)->withQueryString(),
            'filterKey' => $key,
            'filterOptions' => QueueFilters::options($filters, $visible),
            'search' => $search,
        ]);
    }

    public function show(QuestionableExpense $questionableExpense)
    {
        $this->authorize('view', $questionableExpense);

        $questionableExpense->load([
            'status', 'taggedBy', 'attachments.uploader',
            'item.natureOfCost', 'item.attachments.documentType',
            'item.liquidation.preparer', 'item.liquidation.fundRequest',
            'revolvingFundExpenseItem.expense.preparer', 'revolvingFundExpenseItem.expense.voyage.revolvingFund',
            'revolvingFundExpenseItem.natureOfCost',
            'events.actor', 'events.role', 'events.fromStatus', 'events.toStatus',
        ]);

        return view('questionable-expenses.show', ['qe' => $questionableExpense]);
    }

    /** Step 1 */
    public function validateHardCopy(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('validateHardCopy', $questionableExpense);

        $data = $request->validate([
            'hard_copy_valid' => ['required', 'in:1,0'],
            'hard_copy_received_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'required_if:hard_copy_valid,0', 'string', 'max:2000'],
        ], ['remarks.required_if' => 'Say why the hard copy is not valid.']);

        $qe = $this->workflow->validateHardCopy(
            $questionableExpense, Auth::user(), $data['hard_copy_valid'] === '1', $data['hard_copy_received_on'], $data['remarks'] ?? null
        );

        return back()->with('status', $qe->hard_copy_valid
            ? "{$qe->control_no}: hard copy valid — tag changed to Expense and the QE is closed."
            : "{$qe->control_no}: hard copy not valid — kept as Advances to Employee. Generate the Incident Report next.");
    }

    /** Steps 2-3 */
    public function generateIr(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('generateIr', $questionableExpense);

        $data = $request->validate([
            'ir_no' => ['required', 'string', 'max:50'],
            'ir_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $this->workflow->generateIr($questionableExpense, Auth::user(), $data['ir_no'], $data['ir_date']);

        return back()->with('status', 'Incident Report generated — sent to the Approver/OE.');
    }

    /** Step 4 */
    public function receiveIr(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('receiveIr', $questionableExpense);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->receiveIr($questionableExpense, Auth::user(), $data['remarks'] ?? null);

        return back()->with('status', 'Incident Report received — sent to Crewing for salary deduction.');
    }

    /** Step 5 */
    public function subjectToDeduction(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('subjectToDeduction', $questionableExpense);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->subjectToDeduction($questionableExpense, Auth::user(), $data['remarks'] ?? null);

        return back()->with('status', 'Subjected to salary deduction. Upload the proof of deduction next.');
    }

    /** Step 6 */
    public function secureProof(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('secureProof', $questionableExpense);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->secureProof($questionableExpense, Auth::user(), $data['remarks'] ?? null);

        return back()->with('status', 'Proof of deduction secured — sent to Accounting Staff to close.');
    }

    /** Step 7 */
    public function close(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('close', $questionableExpense);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->close($questionableExpense, Auth::user(), $data['remarks'] ?? null);

        return back()->with('status', "{$questionableExpense->control_no} closed — settled by salary deduction.");
    }

    // ---- proof of deduction / authority to deduct (step 6) -------------

    public function storeAttachments(Request $request, QuestionableExpense $questionableExpense)
    {
        $this->authorize('secureProof', $questionableExpense);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ], [
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Allowed types: PDF, JPG, PNG, Word.',
            'files.*.uploaded' => 'Upload failed — the file may exceed the server upload limit (php.ini upload_max_filesize).',
        ]);

        foreach ($request->file('files') as $file) {
            $storedName = Str::uuid().'.'.$file->extension();
            $path = $file->storeAs("attachments/questionable-expenses/{$questionableExpense->id}", $storedName, self::DISK);

            $questionableExpense->attachments()->create([
                'original_file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'stored_file_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }

        return back()->with('status', 'Proof of deduction uploaded.');
    }

    public function downloadAttachment(QuestionableExpense $questionableExpense, Attachment $attachment)
    {
        $this->authorize('view', $questionableExpense);
        $this->assertBelongs($questionableExpense, $attachment);
        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404, 'File not found on storage.');

        return Storage::disk(self::DISK)->download($attachment->file_path, $attachment->original_file_name);
    }

    public function destroyAttachment(QuestionableExpense $questionableExpense, Attachment $attachment)
    {
        $this->authorize('secureProof', $questionableExpense);
        $this->assertBelongs($questionableExpense, $attachment);

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    private function assertBelongs(QuestionableExpense $qe, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === $qe->getMorphClass() && (int) $attachment->attachable_id === $qe->id,
            404
        );
    }

    private function filtersFor(User $user, array $roles): array
    {
        $has = fn (string $code) => in_array($code, $roles, true);
        $in = fn (array $codes) => fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', $codes));

        $queues = [];

        if ($has('ACCT_STAFF')) {
            $queues['staff_hard_copy'] = ['label' => 'Waiting for hard copy', 'apply' => $in([QuestionableExpenseStatus::WAITING_HARD_COPY])];
            $queues['staff_ir'] = ['label' => 'For Incident Report', 'apply' => $in([QuestionableExpenseStatus::FOR_IR])];
            $queues['staff_close'] = ['label' => 'For closure', 'apply' => $in([QuestionableExpenseStatus::FOR_CLOSURE])];
        }

        if ($has('OE')) {
            $queues['oe'] = ['label' => 'Incident Reports to receive', 'apply' => function ($q) use ($user, $in) {
                $in([QuestionableExpenseStatus::FOR_OE_RECEIPT])($q);
                $vesselIds = $user->vessels()->pluck('vessels.id');
                $departmentIds = $user->departments()->pluck('departments.id');
                $inScope = fn ($f) => $f->where(function ($w) use ($vesselIds, $departmentIds) {
                    $w->whereIn('vessel_id', $vesselIds)
                        ->orWhere(fn ($x) => $x->whereNull('vessel_id')->whereIn('department_id', $departmentIds));
                });
                // The cash advance's request, or the revolving fund the QE came from.
                $q->where(fn ($any) => $any
                    ->whereHas('item.liquidation.fundRequest', $inScope)
                    ->orWhereHas('revolvingFundExpenseItem.expense.voyage.revolvingFund', $inScope));
            }];
        }

        if ($has('CREWING')) {
            $queues['crewing_deduct'] = ['label' => 'For salary deduction', 'apply' => $in([QuestionableExpenseStatus::FOR_SALARY_DEDUCTION])];
            $queues['crewing_proof'] = ['label' => 'For proof of deduction', 'apply' => $in([QuestionableExpenseStatus::FOR_PROOF_OF_DEDUCTION])];
        }

        $general = [
            'open' => ['label' => 'Open', 'apply' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('code', '!=', QuestionableExpenseStatus::CLOSED))],
            'closed' => ['label' => 'Closed', 'apply' => $in([QuestionableExpenseStatus::CLOSED])],
            'all' => ['label' => 'All questionable expenses', 'apply' => fn ($q) => $q],
        ];

        return QueueFilters::build($queues, $general);
    }
}
