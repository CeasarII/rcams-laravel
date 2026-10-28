<?php

namespace App\Http\Controllers;

use App\Http\Requests\FundRequestStoreRequest;
use App\Models\User;
use App\Models\FundRequest;
use App\Models\FundRequestType;
use App\Models\RequestStatus;
use App\Models\Vessel;
use App\Services\FundRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FundRequestController extends Controller
{
    public function __construct(private FundRequestWorkflowService $workflow)
    {
    }

    /**
     * One list for every role, narrowed by a "View" dropdown. The dropdown
     * options depend on the user's roles: each role contributes the queue(s)
     * waiting on it, "Needs my action" combines them (and is the default),
     * and everyone also gets the general views (in progress / completed / all).
     *
     * Visibility mirrors FundRequestPolicy::view(): a user whose only role is
     * Requestor never sees anyone else's requests, whatever filter is chosen.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $roles = $user->roles()->pluck('code')->all();
        $has = fn (string $code) => in_array($code, $roles, true);

        $filters = $this->filtersFor($user, $has);

        $key = $request->query('filter');
        if (! isset($filters[$key])) {
            $key = array_key_first($filters);
        }

        $visible = function () use ($user, $has) {
            $q = FundRequest::query();
            $requestorOnly = $has('REQUESTOR')
                && ! $has('OE') && ! $has('ACCT_STAFF') && ! $has('ACCT_HEAD') && ! $has('TREASURY');

            return $requestorOnly ? $q->where('requestor_id', $user->id) : $q;
        };

        $search = trim((string) $request->query('q', ''));

        $query = $visible()
            ->with(['requestor', 'requestStatus', 'fundRequestType', 'vessel'])
            ->latest();
        $filters[$key]['apply']($query);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhereHas('requestor', fn ($r) => $r->where('full_name', 'like', "%{$search}%"));
            });
        }

        // Counts shown in the dropdown (ignores the search box on purpose).
        $options = [];
        foreach ($filters as $k => $f) {
            $countQuery = $visible();
            $f['apply']($countQuery);
            $options[$k] = ['label' => $f['label'], 'count' => $countQuery->count()];
        }

        return view('fund-requests.index', [
            'fundRequests' => $query->paginate(20)->withQueryString(),
            'filterKey' => $key,
            'filterOptions' => $options,
            'search' => $search,
        ]);
    }

    /**
     * @return array<string, array{label: string, apply: \Closure}>
     */
    private function filtersFor(User $user, \Closure $has): array
    {
        $in = fn (array $codes) => fn ($q) => $q->whereHas(
            'requestStatus',
            fn ($s) => $s->whereIn('code', $codes)
        );

        // Queues waiting on this user, by role.
        $queues = [];

        if ($has('REQUESTOR')) {
            $queues['requestor'] = ['label' => 'Drafts & returned to me', 'apply' => function ($q) use ($user, $in) {
                $q->where('requestor_id', $user->id);
                $in([RequestStatus::DRAFT, RequestStatus::RETURNED_TO_REQUESTOR])($q);
            }];
        }

        if ($has('OE')) {
            // Only requests in this OE's scope: their vessels, or (Shore Base) their departments.
            $queues['oe'] = ['label' => 'For OE validation', 'apply' => function ($q) use ($user, $in) {
                $in([RequestStatus::FOR_OE_VALIDATION, RequestStatus::RETURNED_TO_OE])($q);
                $vesselIds = $user->vessels()->pluck('vessels.id');
                $departmentIds = $user->departments()->pluck('departments.id');
                $q->where(function ($w) use ($vesselIds, $departmentIds) {
                    $w->whereIn('vessel_id', $vesselIds)
                        ->orWhere(fn ($x) => $x->whereNull('vessel_id')->whereIn('department_id', $departmentIds));
                });
            }];
        }

        if ($has('ACCT_STAFF')) {
            $queues['staff_validation'] = ['label' => 'For accounting validation', 'apply' =>
                $in([RequestStatus::FOR_ACCT_VALIDATION, RequestStatus::RETURNED_TO_STAFF])];
            $queues['staff_voucher'] = ['label' => 'For AP voucher', 'apply' =>
                $in([RequestStatus::FOR_AP_VOUCHER])];
        }

        if ($has('ACCT_HEAD')) {
            $queues['head'] = ['label' => 'For Head approval', 'apply' =>
                $in([RequestStatus::FOR_HEAD_APPROVAL])];
        }

        if ($has('TREASURY')) {
            $queues['treasury_cv'] = ['label' => 'For CV number', 'apply' =>
                $in([RequestStatus::FOR_CV_NUMBER])];
            $queues['treasury_disburse'] = ['label' => 'For disbursement', 'apply' =>
                $in([RequestStatus::FOR_DISBURSEMENT])];
        }

        $filters = [];

        if ($queues) {
            $filters['action'] = ['label' => 'Needs my action', 'apply' => function ($q) use ($queues) {
                $q->where(function ($w) use ($queues) {
                    foreach ($queues as $queue) {
                        $w->orWhere(fn ($x) => $queue['apply']($x));
                    }
                });
            }];

            // One queue is already what "Needs my action" shows — only list them separately when there are several.
            if (count($queues) > 1) {
                $filters += $queues;
            }
        }

        if ($has('REQUESTOR')) {
            $filters['mine'] = ['label' => 'My requests', 'apply' => fn ($q) => $q->where('requestor_id', $user->id)];
        }

        $filters['in_progress'] = ['label' => 'In progress', 'apply' => fn ($q) => $q->whereHas(
            'requestStatus',
            fn ($s) => $s->whereNotIn('code', [RequestStatus::DRAFT, RequestStatus::COMPLETED, RequestStatus::CANCELLED])
        )];
        $filters['completed'] = ['label' => 'Completed', 'apply' => $in([RequestStatus::COMPLETED])];
        $filters['cancelled'] = ['label' => 'Cancelled', 'apply' => $in([RequestStatus::CANCELLED])];
        $filters['all'] = ['label' => 'All requests', 'apply' => fn ($q) => $q];

        return $filters;
    }

    public function create()
    {
        $this->authorize('create', FundRequest::class);

        return view('fund-requests.create', [
            'requestorDepartment' => Auth::user()->department,
            'vessels' => $this->vesselsFor(Auth::user()),
            'revolvingFunds' => $this->revolvingFundsFor(Auth::user()),
            'fundRequestTypes' => FundRequestType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(FundRequestStoreRequest $request)
    {
        $fundRequest = $this->workflow->create($request->validated(), Auth::user());

        return redirect()
            ->route('fund-requests.show', $fundRequest)
            ->with('status', "Draft {$fundRequest->control_no} created.");
    }

    public function show(FundRequest $fundRequest)
    {
        $this->authorize('view', $fundRequest);

        $fundRequest->load([
            'requestor', 'department', 'company', 'vessel', 'fundRequestType',
            'requestStatus', 'details', 'attachments.uploader',
            'approvals.approver', 'approvals.role', 'approvals.approvalStatus',
            'apVoucher.checkVoucher',
        ]);

        return view('fund-requests.show', ['fundRequest' => $fundRequest]);
    }

    public function edit(FundRequest $fundRequest)
    {
        $this->authorize('update', $fundRequest);

        $fundRequest->load('details');

        return view('fund-requests.edit', [
            'fundRequest' => $fundRequest,
            'requestorDepartment' => Auth::user()->department,
            'vessels' => $this->vesselsFor(Auth::user(), $fundRequest->vessel_id),
            'revolvingFunds' => $this->revolvingFundsFor(Auth::user(), $fundRequest->revolving_fund_id),
            'fundRequestTypes' => FundRequestType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Only content changes while the record is Draft/Returned — see
     * FundRequestPolicy::update(). Re-sums the detail lines on every save;
     * amount_requested is never accepted from the form.
     */
    public function update(FundRequestStoreRequest $request, FundRequest $fundRequest)
    {
        $this->authorize('update', $fundRequest);

        $data = $request->validated();

        $fundRequest->update([
            'department_id' => $data['department_id'],
            'category' => $data['category'],
            'vessel_id' => $data['vessel_id'] ?? null,
            'revolving_fund_id' => $data['revolving_fund_id'] ?? null,
            'fund_request_type_id' => $data['fund_request_type_id'],
            'request_date' => $data['request_date'],
            'purpose' => $data['purpose'],
        ]);

        $fundRequest->details()->delete();
        foreach ($data['details'] as $line) {
            $fundRequest->details()->create($line);
        }
        $fundRequest->recalculateAmount();

        return redirect()
            ->route('fund-requests.show', $fundRequest)
            ->with('status', "{$fundRequest->control_no} updated.");
    }

    /**
     * The vessels this requestor is assigned to (user_vessels, active only).
     * On edit the request's current vessel is kept in the list even if it was
     * deactivated or unassigned since, so saving doesn't silently change it.
     */
    private function vesselsFor(User $user, ?int $currentVesselId = null)
    {
        return Vessel::query()
            ->where(function ($q) use ($user, $currentVesselId) {
                $q->whereIn('id', $user->vessels()->where('vessels.is_active', true)->pluck('vessels.id'));
                if ($currentVesselId) {
                    $q->orWhere('id', $currentVesselId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /** Revolving funds this user is custodian of — what a Revolving Fund request can fund. */
    private function revolvingFundsFor(User $user, ?int $currentFundId = null)
    {
        return \App\Models\RevolvingFund::query()
            ->with('vessel')
            ->where(function ($q) use ($user, $currentFundId) {
                $q->where(fn ($own) => $own->where('custodian_id', $user->id)
                    ->whereHas('status', fn ($s) => $s->where('code', \App\Models\RevolvingFundStatus::ACTIVE)));
                if ($currentFundId) {
                    $q->orWhere('id', $currentFundId);
                }
            })
            ->orderBy('fund_name')
            ->get();
    }
}
