<?php

namespace App\Http\Controllers;

use App\Models\RevolvingFundExpense;
use App\Models\RevolvingFundExpenseStatus as S;
use App\Models\RevolvingFundVoyage;
use App\Models\User;
use App\Services\RevolvingFundExpenseWorkflowService;
use App\Support\LiquidationAccess;
use App\Support\QueueFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevolvingFundExpenseController extends Controller
{
    public function __construct(private RevolvingFundExpenseWorkflowService $workflow)
    {
    }

    /** Same pattern as the other lists: a role-aware "View" dropdown, default "Needs my action". */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = LiquidationAccess::roles($user);
        $filters = $this->filtersFor($user, $roles);
        $key = QueueFilters::resolve($filters, $request->query('filter'));

        $visible = function () use ($user, $roles) {
            $q = RevolvingFundExpense::query();

            return LiquidationAccess::seesAll($roles) ? $q : $q->where('prepared_by', $user->id);
        };

        $search = trim((string) $request->query('q', ''));

        $query = $visible()->with(['voyage.revolvingFund', 'preparer', 'status'])->latest();
        $filters[$key]['apply']($query);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhereHas('preparer', fn ($r) => $r->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('voyage', fn ($v) => $v->where('voyage_no', 'like', "%{$search}%")
                        ->orWhereHas('revolvingFund', fn ($f) => $f->where('fund_name', 'like', "%{$search}%")->orWhere('fund_code', 'like', "%{$search}%")));
            });
        }

        return view('revolving-fund-expenses.index', [
            'expenses' => $query->paginate(20)->withQueryString(),
            'filterKey' => $key,
            'filterOptions' => QueueFilters::options($filters, $visible),
            'search' => $search,
        ]);
    }

    public function show(RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('view', $revolvingFundExpense);

        $revolvingFundExpense->load([
            'status', 'preparer', 'voucher',
            'voyage.revolvingFund.vessel', 'voyage.revolvingFund.department', 'voyage.revolvingFund.custodian',
            'items.status', 'items.natureOfCost', 'items.attachments.uploader', 'items.questionableExpense.status',
            'approvals.approver', 'approvals.role', 'approvals.approvalStatus', 'approvals.fromStatus', 'approvals.toStatus',
        ]);

        return view('revolving-fund-expenses.show', ['expense' => $revolvingFundExpense]);
    }

    /** Step 1 — start preparing the voyage expense details for an open voyage. */
    public function store(RevolvingFundVoyage $voyage)
    {
        $this->authorize('manageVoyage', $voyage->revolvingFund);

        $expense = $this->workflow->create($voyage, Auth::user());

        return redirect()
            ->route('revolving-fund-expenses.show', $expense)
            ->with('status', "{$expense->control_no} started for voyage {$voyage->voyage_no}. Add the expense items.");
    }

    public function edit(RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('update', $revolvingFundExpense);

        return view('revolving-fund-expenses.edit', ['expense' => $revolvingFundExpense->load('voyage.revolvingFund')]);
    }

    public function update(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('update', $revolvingFundExpense);

        $data = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $revolvingFundExpense->update($data);

        return redirect()->route('revolving-fund-expenses.show', $revolvingFundExpense)->with('status', 'Details saved.');
    }

    private function filtersFor(User $user, array $roles): array
    {
        $has = fn (string $code) => in_array($code, $roles, true);
        $in = fn (array $codes) => fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', $codes));

        $queues = [];

        if ($has('REQUESTOR')) {
            $queues['requestor'] = ['label' => 'Drafts & returned to me', 'apply' => function ($q) use ($user, $in) {
                $q->where('prepared_by', $user->id);
                $in(S::REQUESTOR_EDITABLE)($q);
            }];
        }

        if ($has('OE')) {
            // Only the funds on this OE's vessels (or, Shore Base, departments).
            $queues['oe'] = ['label' => 'For OE validation', 'apply' => function ($q) use ($user, $in) {
                $in([S::FOR_OE_VALIDATION, S::RETURNED_TO_OE])($q);
                $vesselIds = $user->vessels()->pluck('vessels.id');
                $departmentIds = $user->departments()->pluck('departments.id');
                $q->whereHas('voyage.revolvingFund', fn ($f) => $f->where(function ($w) use ($vesselIds, $departmentIds) {
                    $w->whereIn('vessel_id', $vesselIds)
                        ->orWhere(fn ($x) => $x->whereNull('vessel_id')->whereIn('department_id', $departmentIds));
                }));
            }];
        }

        if ($has('ACCT_STAFF')) {
            $queues['staff_validate'] = ['label' => 'For validation', 'apply' => $in([S::FOR_STAFF_VALIDATION, S::RETURNED_TO_STAFF])];
            $queues['staff_apv'] = ['label' => 'For AP voucher', 'apply' => $in([S::FOR_AP_VOUCHER])];
        }

        if ($has('ACCT_HEAD')) {
            $queues['head_approve'] = ['label' => 'For approval', 'apply' => $in([S::FOR_HEAD_APPROVAL])];
            $queues['head_tag'] = ['label' => 'For HC pending tag', 'apply' => $in([S::FOR_HC_TAG])];
        }

        if ($has('TREASURY')) {
            $queues['treasury_cv'] = ['label' => 'For CV number', 'apply' => $in([S::FOR_CV_NUMBER])];
            $queues['treasury_disburse'] = ['label' => 'For disbursement', 'apply' => $in([S::FOR_DISBURSEMENT])];
        }

        $general = [];

        if ($has('REQUESTOR')) {
            $general['mine'] = ['label' => 'My voyage expenses', 'apply' => fn ($q) => $q->where('prepared_by', $user->id)];
        }

        $general['in_progress'] = ['label' => 'In progress', 'apply' => fn ($q) => $q->whereHas(
            'status', fn ($s) => $s->whereNotIn('code', [S::DRAFT, S::COMPLETED])
        )];
        $general['completed'] = ['label' => 'Completed', 'apply' => $in([S::COMPLETED])];
        $general['all'] = ['label' => 'All voyage expenses', 'apply' => fn ($q) => $q];

        return QueueFilters::build($queues, $general);
    }
}
