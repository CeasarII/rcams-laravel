<?php

namespace App\Services;

use App\Models\ApprovalStatus;
use App\Models\ApVoucher;
use App\Models\CheckVoucher;
use App\Models\FundRequest;
use App\Models\FundRequestApproval;
use App\Models\AppNotification;
use App\Models\RequestStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Implements the Process A transition table from Phase 1 §4.
 * Every public method here is one workflow action: it must run inside a
 * DB transaction, verify the caller may act on the record's current
 * status, move the status, write one FundRequestApproval history row,
 * and raise a notification for whoever holds the record next.
 *
 * Money is never taken from request input — recalculateAmount() always
 * re-sums fund_request_details, and amount_requested is locked once the
 * request leaves Draft (enforced by FundRequestPolicy::update()).
 */
class FundRequestWorkflowService
{
    public function __construct(private ControlNoGenerator $controlNoGenerator)
    {
    }

    /**
     * Creates a Draft fund request with its detail lines. Accepts validated
     * data only — call this from FundRequestStoreRequest's validated(), never
     * raw request input, since amount_requested is recalculated server-side
     * regardless of what was posted.
     *
     * @param  array{department_id:int, category:string, vessel_id:?int, fund_request_type_id:int, request_date:string, purpose:string, details: array<int, array{purpose:string, description:?string, amount:float}>}  $data
     */
    public function create(array $data, User $requestor): FundRequest
    {
        return DB::transaction(function () use ($data, $requestor) {
            $fundRequestType = \App\Models\FundRequestType::findOrFail($data['fund_request_type_id']);

            $companyId = $data['category'] === FundRequest::CATEGORY_SEA_BASE
                ? \App\Models\Vessel::findOrFail($data['vessel_id'])->company_id
                : $requestor->company_id;

            $draftStatus = RequestStatus::where('code', RequestStatus::DRAFT)->firstOrFail();

            $fundRequest = FundRequest::create([
                'control_no' => $this->controlNoGenerator->next($fundRequestType->code),
                'requestor_id' => $requestor->id,
                'department_id' => $data['department_id'],
                'company_id' => $companyId,
                'category' => $data['category'],
                'vessel_id' => $data['vessel_id'] ?? null,
                'revolving_fund_id' => $data['revolving_fund_id'] ?? null,
                'fund_request_type_id' => $fundRequestType->id,
                'amount_requested' => 0,
                'request_date' => $data['request_date'],
                'purpose' => $data['purpose'],
                'request_status_id' => $draftStatus->id,
            ]);

            foreach ($data['details'] as $line) {
                $fundRequest->details()->create($line);
            }

            $fundRequest->recalculateAmount();

            return $fundRequest->refresh();
        });
    }

    public function submit(FundRequest $fundRequest, User $actor): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::DRAFT, RequestStatus::RETURNED_TO_REQUESTOR]);
        $this->assertOwner($fundRequest, $actor);

        return DB::transaction(function () use ($fundRequest, $actor) {
            $fundRequest->recalculateAmount();

            if ($fundRequest->amount_requested <= 0) {
                throw ValidationException::withMessages([
                    'amount_requested' => 'A fund request needs at least one detail line with an amount.',
                ]);
            }

            $isFirstSubmit = is_null($fundRequest->submitted_at);

            $this->transition(
                $fundRequest,
                $actor,
                RequestStatus::FOR_OE_VALIDATION,
                ApprovalStatus::SUBMITTED,
                null,
                roleCode: 'REQUESTOR',
            );

            if ($isFirstSubmit) {
                $fundRequest->submitted_at = now();
                $fundRequest->save();
            }

            $this->notifyOe($fundRequest, 'Fund request for validation', $fundRequest->control_no . ' is ready for OE validation.');

            return $fundRequest->refresh();
        });
    }

    public function validateByOe(FundRequest $fundRequest, User $actor, ?string $comments = null): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_OE_VALIDATION, RequestStatus::RETURNED_TO_OE]);
        $this->assertOeScope($fundRequest, $actor);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::FOR_ACCT_VALIDATION, ApprovalStatus::VALIDATED, $comments, roleCode: 'OE');
            $this->notifyRole($fundRequest, 'ACCT_STAFF', 'Fund request for validation', $fundRequest->control_no . ' is ready for Accounting validation.');
            return $fundRequest->refresh();
        });
    }

    public function returnToRequestor(FundRequest $fundRequest, User $actor, string $comments): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_OE_VALIDATION, RequestStatus::RETURNED_TO_OE]);
        $this->assertOeScope($fundRequest, $actor);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::RETURNED_TO_REQUESTOR, ApprovalStatus::RETURNED, $comments, roleCode: 'OE');
            $this->notifyUser($fundRequest->requestor_id, $fundRequest, 'Fund request returned', "{$fundRequest->control_no} was returned: {$comments}");
            return $fundRequest->refresh();
        });
    }

    public function validateByStaff(FundRequest $fundRequest, User $actor, ?string $comments = null): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_ACCT_VALIDATION, RequestStatus::RETURNED_TO_STAFF]);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::FOR_HEAD_APPROVAL, ApprovalStatus::VALIDATED, $comments, roleCode: 'ACCT_STAFF');
            $this->notifyRole($fundRequest, 'ACCT_HEAD', 'Fund request for approval', $fundRequest->control_no . ' is ready for Head approval.');
            return $fundRequest->refresh();
        });
    }

    public function returnToOe(FundRequest $fundRequest, User $actor, string $comments): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_ACCT_VALIDATION, RequestStatus::RETURNED_TO_STAFF]);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::RETURNED_TO_OE, ApprovalStatus::RETURNED, $comments, roleCode: 'ACCT_STAFF');
            $this->notifyOe($fundRequest, 'Fund request returned', "{$fundRequest->control_no} was returned: {$comments}");
            return $fundRequest->refresh();
        });
    }

    public function approve(FundRequest $fundRequest, User $actor, ?string $comments = null): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_HEAD_APPROVAL]);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::FOR_AP_VOUCHER, ApprovalStatus::APPROVED, $comments, roleCode: 'ACCT_HEAD');
            $this->notifyRole($fundRequest, 'ACCT_STAFF', 'Fund request approved', $fundRequest->control_no . ' was approved — create the AP Voucher.');
            return $fundRequest->refresh();
        });
    }

    public function notApprove(FundRequest $fundRequest, User $actor, string $comments): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_HEAD_APPROVAL]);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::RETURNED_TO_STAFF, ApprovalStatus::NOT_APPROVED, $comments, roleCode: 'ACCT_HEAD');
            $this->notifyRole($fundRequest, 'ACCT_STAFF', 'Fund request not approved', "{$fundRequest->control_no} was not approved: {$comments}");
            return $fundRequest->refresh();
        });
    }

    public function createApVoucher(FundRequest $fundRequest, User $actor, string $apvNo, ?string $apvDate = null): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_AP_VOUCHER]);

        return DB::transaction(function () use ($fundRequest, $actor, $apvNo, $apvDate) {
            ApVoucher::create([
                'fund_request_id' => $fundRequest->id,
                'apv_no' => $apvNo,
                'apv_date' => $apvDate ?? now()->toDateString(),
                'amount' => $fundRequest->amount_requested,
                'created_by' => $actor->id,
            ]);

            $this->transition($fundRequest, $actor, RequestStatus::FOR_CV_NUMBER, ApprovalStatus::APV_CREATED, null, roleCode: 'ACCT_STAFF');
            $this->notifyRole($fundRequest, 'TREASURY', 'AP Voucher created', $fundRequest->control_no . ' is ready for a CV number.');
            return $fundRequest->refresh();
        });
    }

    public function provideCvNumber(FundRequest $fundRequest, User $actor, string $cvNo): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_CV_NUMBER]);

        return DB::transaction(function () use ($fundRequest, $actor, $cvNo) {
            CheckVoucher::create([
                'ap_voucher_id' => $fundRequest->apVoucher->id,
                'cv_no' => $cvNo,
                'cv_date' => now()->toDateString(),
                'processed_by' => $actor->id,
            ]);

            $this->transition($fundRequest, $actor, RequestStatus::FOR_DISBURSEMENT, ApprovalStatus::CV_CREATED, null, roleCode: 'TREASURY');
            return $fundRequest->refresh();
        });
    }

    public function disburse(FundRequest $fundRequest, User $actor, ?string $paymentMode = null, ?string $paymentReference = null): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::FOR_DISBURSEMENT]);

        return DB::transaction(function () use ($fundRequest, $actor, $paymentMode, $paymentReference) {
            $checkVoucher = $fundRequest->apVoucher->checkVoucher;
            $checkVoucher->update([
                'amount_disbursed' => $fundRequest->amount_requested,
                'disbursed_at' => now(),
                'payment_mode' => $paymentMode,
                'payment_reference' => $paymentReference,
            ]);

            $fundRequest->completed_at = now();
            $this->transition($fundRequest, $actor, RequestStatus::COMPLETED, ApprovalStatus::DISBURSED, null, roleCode: 'TREASURY');

            // A disbursed Revolving Fund request funds that revolving fund: one FUNDING row in its ledger.
            if ($fundRequest->revolving_fund_id) {
                app(RevolvingFundLedger::class)->post(
                    $fundRequest->revolvingFund,
                    \App\Models\RevolvingFundTransactionType::FUNDING,
                    $fundRequest->amount_requested,
                    [
                        'fund_request_id' => $fundRequest->id,
                        'reference_no' => $checkVoucher->cv_no,
                        'description' => "Funded by {$fundRequest->control_no}",
                        'posted_by' => $actor->id,
                    ]
                );
            }

            // Step 8: notify the Requestor. Done last, after the record is Completed.
            $this->notifyUser($fundRequest->requestor_id, $fundRequest, 'Fund request disbursed', "{$fundRequest->control_no} has been disbursed. This request is now complete.");

            return $fundRequest->refresh();
        });
    }

    public function cancel(FundRequest $fundRequest, User $actor, string $comments): FundRequest
    {
        $this->assertStatus($fundRequest, [RequestStatus::DRAFT, RequestStatus::RETURNED_TO_REQUESTOR]);
        $this->assertOwner($fundRequest, $actor);

        return DB::transaction(function () use ($fundRequest, $actor, $comments) {
            $this->transition($fundRequest, $actor, RequestStatus::CANCELLED, ApprovalStatus::CANCELLED, $comments, roleCode: 'REQUESTOR');
            return $fundRequest->refresh();
        });
    }

    // ---- internals ----------------------------------------------------

    private function transition(
        FundRequest $fundRequest,
        User $actor,
        string $toStatusCode,
        string $approvalStatusCode,
        ?string $comments,
        string $roleCode,
    ): void {
        $fromStatus = RequestStatus::where('code', $fundRequest->requestStatus->code)->first();
        $toStatus = RequestStatus::where('code', $toStatusCode)->firstOrFail();
        $approvalStatus = ApprovalStatus::where('code', $approvalStatusCode)->firstOrFail();
        $role = Role::where('code', $roleCode)->firstOrFail();

        $fundRequest->request_status_id = $toStatus->id;
        $fundRequest->save();

        FundRequestApproval::create([
            'fund_request_id' => $fundRequest->id,
            'approver_id' => $actor->id,
            'role_id' => $role->id,
            'approval_level' => null,
            'approval_status_id' => $approvalStatus->id,
            'from_request_status_id' => $fromStatus?->id,
            'to_request_status_id' => $toStatus->id,
            'approval_date' => now(),
            'comments' => $comments,
        ]);
    }

    private function assertStatus(FundRequest $fundRequest, array $allowedCodes): void
    {
        if (! in_array($fundRequest->requestStatus->code, $allowedCodes, true)) {
            throw ValidationException::withMessages([
                'status' => "This action isn't valid from the request's current status ({$fundRequest->requestStatus->label}).",
            ]);
        }
    }

    private function assertOwner(FundRequest $fundRequest, User $actor): void
    {
        if ($fundRequest->requestor_id !== $actor->id) {
            throw ValidationException::withMessages([
                'requestor' => 'Only the original Requestor may act on this request.',
            ]);
        }
    }

    /**
     * OE scoping: Sea Base requests must be validated by an OE assigned to
     * that vessel (user_vessels). Shore Base requests must be validated by
     * an OE assigned to the requestor's department (user_departments).
     * An OE with no scope rows at all can't validate anything — assign
     * every OE to at least one vessel or department.
     */
    private function assertOeScope(FundRequest $fundRequest, User $actor): void
    {
        $scoped = $fundRequest->vessel_id !== null
            ? $actor->vessels()->where('vessels.id', $fundRequest->vessel_id)->exists()
            : $actor->departments()->where('departments.id', $fundRequest->department_id)->exists();

        if (! $scoped) {
            throw ValidationException::withMessages([
                'scope' => $fundRequest->vessel_id !== null
                    ? 'You are not assigned to validate requests for this vessel.'
                    : 'You are not assigned to validate requests for this department.',
            ]);
        }
    }

    /** Notifies only the OE(s) scoped to this request's vessel or department — never every OE. */
    private function notifyOe(FundRequest $fundRequest, string $title, string $message): void
    {
        $userIds = $fundRequest->vessel_id !== null
            ? User::whereHas('roles', fn ($q) => $q->where('code', 'OE'))
                ->whereHas('vessels', fn ($q) => $q->where('vessels.id', $fundRequest->vessel_id))
                ->pluck('id')
            : User::whereHas('roles', fn ($q) => $q->where('code', 'OE'))
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $fundRequest->department_id))
                ->pluck('id');

        foreach ($userIds as $userId) {
            $this->notifyUser($userId, $fundRequest, $title, $message);
        }
    }

    private function notifyRole(FundRequest $fundRequest, string $roleCode, string $title, string $message): void
    {
        $userIds = User::whereHas('roles', fn ($q) => $q->where('code', $roleCode))->pluck('id');

        foreach ($userIds as $userId) {
            $this->notifyUser($userId, $fundRequest, $title, $message);
        }
    }

    private function notifyUser(int $userId, FundRequest $fundRequest, string $title, string $message): void
    {
        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'reference_type' => FundRequest::class,
            'reference_id' => $fundRequest->id,
            'is_read' => false,
        ]);
    }
}
