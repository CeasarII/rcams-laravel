<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceLiquidation;
use App\Models\FundRequest;
use App\Models\LiquidationStatus;
use App\Models\QuestionableExpenseStatus;
use App\Models\User;
use App\Services\LiquidationWorkflowService;
use App\Support\LiquidationAccess;
use App\Support\QueueFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiquidationController extends Controller
{
    public function __construct(private LiquidationWorkflowService $workflow)
    {
    }

    /** Same pattern as the fund request list: a role-aware "View" dropdown, default "Needs my action". */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = LiquidationAccess::roles($user);
        $filters = $this->filtersFor($user, $roles);
        $key = QueueFilters::resolve($filters, $request->query('filter'));

        $visible = fn () => LiquidationAccess::scopeLiquidations(CashAdvanceLiquidation::query(), $user, $roles);

        $search = trim((string) $request->query('q', ''));

        $query = $visible()->with(['fundRequest.requestor', 'fundRequest.vessel', 'status', 'preparer'])->latest();
        $filters[$key]['apply']($query);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhereHas('preparer', fn ($r) => $r->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('fundRequest', fn ($r) => $r->where('control_no', 'like', "%{$search}%"));
            });
        }

        return view('liquidations.index', [
            'liquidations' => $query->paginate(20)->withQueryString(),
            'filterKey' => $key,
            'filterOptions' => QueueFilters::options($filters, $visible),
            'search' => $search,
        ]);
    }

    public function show(CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('view', $liquidation);

        $liquidation->load([
            'status', 'preparer', 'voucher',
            'fundRequest.requestor', 'fundRequest.vessel', 'fundRequest.department',
            'items.status', 'items.natureOfCost', 'items.attachments.documentType', 'items.attachments.uploader',
            'items.questionableExpense.status',
            'approvals.approver', 'approvals.role', 'approvals.approvalStatus', 'approvals.fromStatus', 'approvals.toStatus',
        ]);

        return view('liquidations.show', [
            'liquidation' => $liquidation,
            'heldAsAdvances' => $liquidation->heldAsAdvances(),
        ]);
    }

    /** "Comes from the Cash Advance request first": started from a disbursed cash advance. */
    public function store(FundRequest $fundRequest)
    {
        $this->authorize('liquidate', $fundRequest);

        $liquidation = $this->workflow->create($fundRequest, Auth::user());

        return redirect()
            ->route('liquidations.show', $liquidation)
            ->with('status', "{$liquidation->control_no} started for {$fundRequest->control_no}. Add your expense items.");
    }

    public function edit(CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('update', $liquidation);

        return view('liquidations.edit', ['liquidation' => $liquidation->load('fundRequest')]);
    }

    public function update(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('update', $liquidation);

        $data = $request->validate([
            'date_prepared' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:2000'],
        ]);

        $liquidation->update($data);

        return redirect()->route('liquidations.show', $liquidation)->with('status', 'Liquidation details saved.');
    }

    private function filtersFor(User $user, array $roles): array
    {
        $has = fn (string $code) => in_array($code, $roles, true);
        $in = fn (array $codes) => fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', $codes));

        $queues = [];

        if ($has('REQUESTOR')) {
            $queues['requestor'] = ['label' => 'Drafts & returned to me', 'apply' => function ($q) use ($user, $in) {
                $q->where('prepared_by', $user->id);
                $in(LiquidationStatus::EDITABLE)($q);
            }];
        }

        if ($has('OE')) {
            // Only liquidations whose cash advance is on this OE's vessels (or, Shore Base, departments).
            $queues['oe'] = ['label' => 'For OE validation', 'apply' => function ($q) use ($user, $in) {
                $in([LiquidationStatus::FOR_OE_VALIDATION])($q);
                $vesselIds = $user->vessels()->pluck('vessels.id');
                $departmentIds = $user->departments()->pluck('departments.id');
                $q->whereHas('fundRequest', fn ($f) => $f->where(function ($w) use ($vesselIds, $departmentIds) {
                    $w->whereIn('vessel_id', $vesselIds)
                        ->orWhere(fn ($x) => $x->whereNull('vessel_id')->whereIn('department_id', $departmentIds));
                }));
            }];
        }

        if ($has('ACCT_STAFF')) {
            $queues['staff_verify'] = ['label' => 'For verification', 'apply' => $in([LiquidationStatus::FOR_STAFF_VERIFICATION])];
            $queues['staff_apv'] = ['label' => 'For APV preparation', 'apply' => $in([LiquidationStatus::FOR_APV_PREPARATION])];
        }

        if ($has('ACCT_HEAD')) {
            $queues['head_review'] = ['label' => 'For APV review', 'apply' => $in([LiquidationStatus::FOR_APV_REVIEW])];
            $queues['head_close'] = ['label' => 'For closure', 'apply' => $in([LiquidationStatus::FOR_HEAD_CLOSURE])];
        }

        if ($has('TREASURY')) {
            $queues['treasury'] = ['label' => 'For reimbursement', 'apply' => $in([LiquidationStatus::FOR_REIMBURSEMENT])];
        }

        $general = [];

        if ($has('REQUESTOR')) {
            $general['mine'] = ['label' => 'My liquidations', 'apply' => fn ($q) => $q->where('prepared_by', $user->id)];
        }

        $general['in_progress'] = ['label' => 'In progress', 'apply' => fn ($q) => $q->whereHas(
            'status', fn ($s) => $s->whereNotIn('code', [LiquidationStatus::DRAFT, LiquidationStatus::CLOSED])
        )];
        $general['open_qe'] = ['label' => 'With open questionable expenses', 'apply' => fn ($q) => $q->whereHas(
            'questionableExpenses', fn ($qe) => $qe->whereHas('status', fn ($s) => $s->where('code', '!=', QuestionableExpenseStatus::CLOSED))
        )];
        $general['closed'] = ['label' => 'Closed', 'apply' => $in([LiquidationStatus::CLOSED])];
        $general['all'] = ['label' => 'All liquidations', 'apply' => fn ($q) => $q];

        return QueueFilters::build($queues, $general);
    }
}
