<?php

namespace App\Services;

use App\Models\ApprovalStatus;
use App\Models\CashAdvanceLiquidation;
use App\Models\FundRequest;
use App\Models\FundRequestType;
use App\Models\LiquidationApproval;
use App\Models\LiquidationItem;
use App\Models\LiquidationItemStatus;
use App\Models\LiquidationStatus;
use App\Models\LiquidationVoucher;
use App\Models\QuestionableExpense;
use App\Models\QuestionableExpenseStatus;
use App\Models\RequestStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cash Advance Liquidation, following the process guide:
 *
 *   Requestor  Enter details / expense breakdown  -> submit
 *   OE         Validate. "Liquidation okay?"  No -> Edit liquidation (Requestor) ; Yes -> Staff
 *   Staff      Verify.   "Verification okay?" No -> Revise liquidation (Requestor) ; Yes ->
 *              "payable to requestor?"  Yes -> Prepare APV -> Head reviews APV
 *                                        (Review okay? No -> back to Staff verification ; Yes -> Treasury)
 *                                       No  -> Head: Process liquidation and tag as close
 *   Treasury   Process reimbursement, input check voucher, notify Requestor -> Closed
 *
 * At "Liquidation okay?" (OE) and "Verification okay?" (Staff) an item can be
 * tagged as a Questionable Expense, which then runs its own process
 * (QuestionableExpenseWorkflowService) while the liquidation carries on.
 *
 * Every action runs in a transaction, re-checks the status, writes one
 * LiquidationApproval history row and notifies whoever holds it next.
 */
class LiquidationWorkflowService
{
    use Concerns\NotifiesUsers;

    public function __construct(private ControlNoGenerator $controlNo)
    {
    }

    /** Starts a Draft liquidation from a disbursed Cash Advance. */
    public function create(FundRequest $fundRequest, User $actor, array $data = []): CashAdvanceLiquidation
    {
        $fundRequest->loadMissing('fundRequestType', 'requestStatus');

        if ($fundRequest->fundRequestType->code !== FundRequestType::CASH_ADVANCE) {
            throw self::fail('fund_request', 'Only a Cash Advance can be liquidated.');
        }
        if ($fundRequest->requestStatus->code !== RequestStatus::COMPLETED) {
            throw self::fail('fund_request', 'The cash advance must be disbursed (Completed) before it can be liquidated.');
        }
        if ($fundRequest->requestor_id !== $actor->id) {
            throw self::fail('fund_request', 'Only the Requestor of this cash advance may start its liquidation.');
        }
        if (CashAdvanceLiquidation::where('fund_request_id', $fundRequest->id)->exists()) {
            throw self::fail('fund_request', 'This cash advance already has a liquidation.');
        }

        return DB::transaction(function () use ($fundRequest, $actor, $data) {
            return CashAdvanceLiquidation::create([
                'control_no' => $this->controlNo->nextLiquidation(),
                'fund_request_id' => $fundRequest->id,
                'prepared_by' => $actor->id,
                'date_prepared' => $data['date_prepared'] ?? now()->toDateString(),
                'purpose' => $data['purpose'] ?? $fundRequest->purpose,
                'cash_advance_amount' => $fundRequest->amount_requested,
                'liquidation_status_id' => LiquidationStatus::where('code', LiquidationStatus::DRAFT)->firstOrFail()->id,
            ])->refresh();
        });
    }

    public function submit(CashAdvanceLiquidation $liq, User $actor): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, LiquidationStatus::EDITABLE);
        $this->assertOwner($liq, $actor);

        return DB::transaction(function () use ($liq, $actor) {
            $items = $liq->items()->withCount('attachments')->get();

            if ($items->isEmpty()) {
                throw self::fail('items', 'Add at least one expense item before submitting.');
            }

            $unsupported = $items->where('attachments_count', 0);
            if ($unsupported->isNotEmpty()) {
                throw self::fail('attachments', 'Every item needs at least one supporting document (soft copy). Missing on: '
                    .$unsupported->pluck('purpose')->take(3)->implode(', ').($unsupported->count() > 3 ? '…' : '.'));
            }

            $liq->recalculateTotals();
            $first = is_null($liq->submitted_at);

            $this->transition($liq, $actor, LiquidationStatus::FOR_OE_VALIDATION, ApprovalStatus::SUBMITTED, null, 'REQUESTOR');

            if ($first) {
                $liq->submitted_at = now();
                $liq->save();
            }

            $this->notifyOe($liq->fundRequest, $liq, 'Liquidation for validation', "{$liq->control_no} is ready for OE validation.");

            return $liq->refresh();
        });
    }

    /** OE: "Liquidation okay?" = Yes. */
    public function validateByOe(CashAdvanceLiquidation $liq, User $actor, ?string $comments = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_OE_VALIDATION]);
        $this->assertOeScope($liq->fundRequest, $actor);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $this->transition($liq, $actor, LiquidationStatus::FOR_STAFF_VERIFICATION, ApprovalStatus::VALIDATED, $comments, 'OE');
            $this->notifyRole('ACCT_STAFF', $liq, 'Liquidation for verification', "{$liq->control_no} is ready for Accounting verification.");

            return $liq->refresh();
        });
    }

    /** OE: "Liquidation okay?" = No -> Edit liquidation. */
    public function returnForEdit(CashAdvanceLiquidation $liq, User $actor, string $comments): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_OE_VALIDATION]);
        $this->assertOeScope($liq->fundRequest, $actor);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $this->transition($liq, $actor, LiquidationStatus::RETURNED_FOR_EDIT, ApprovalStatus::RETURNED, $comments, 'OE');
            $this->notifyUser($liq->prepared_by, $liq, 'Liquidation returned for edit', "{$liq->control_no} was returned: {$comments}");

            return $liq->refresh();
        });
    }

    /**
     * Staff: "Verification okay?" = Yes. The "payable to requestor?" decision is
     * made from the figures: the company owes the requestor only when valid
     * expenses exceed the advance.
     */
    public function verifyByStaff(CashAdvanceLiquidation $liq, User $actor, ?string $comments = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_STAFF_VERIFICATION]);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $liq->recalculateTotals(); // last time the totals follow the items

            $payable = (float) $liq->amount_to_reimburse > 0;

            $this->transition(
                $liq, $actor,
                $payable ? LiquidationStatus::FOR_APV_PREPARATION : LiquidationStatus::FOR_HEAD_CLOSURE,
                ApprovalStatus::VALIDATED, $comments, 'ACCT_STAFF'
            );

            if (! $payable) {
                $this->notifyRole('ACCT_HEAD', $liq, 'Liquidation for closure', "{$liq->control_no} has nothing payable to the requestor — ready to close.");
            }

            return $liq->refresh();
        });
    }

    /** Staff: "Verification okay?" = No -> Revise liquidation. */
    public function returnForRevision(CashAdvanceLiquidation $liq, User $actor, string $comments): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_STAFF_VERIFICATION]);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $this->transition($liq, $actor, LiquidationStatus::RETURNED_FOR_REVISION, ApprovalStatus::RETURNED, $comments, 'ACCT_STAFF');
            $this->notifyUser($liq->prepared_by, $liq, 'Liquidation returned for revision', "{$liq->control_no} was returned: {$comments}");

            return $liq->refresh();
        });
    }

    /** Staff: Prepare accounts payable voucher (amount owed to the requestor). */
    public function prepareApv(CashAdvanceLiquidation $liq, User $actor, string $apvNo, ?string $apvDate = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_APV_PREPARATION]);

        return DB::transaction(function () use ($liq, $actor, $apvNo, $apvDate) {
            // updateOrCreate: a rejected APV is re-prepared on the same row.
            LiquidationVoucher::updateOrCreate(
                ['liquidation_id' => $liq->id],
                [
                    'apv_no' => $apvNo,
                    'apv_date' => $apvDate ?? now()->toDateString(),
                    'amount' => $liq->amount_to_reimburse,
                    'prepared_by' => $actor->id,
                ]
            );

            $this->transition($liq, $actor, LiquidationStatus::FOR_APV_REVIEW, ApprovalStatus::APV_CREATED, null, 'ACCT_STAFF');
            $this->notifyRole('ACCT_HEAD', $liq, 'APV for review', "{$liq->control_no}: review the accounts payable voucher {$apvNo}.");

            return $liq->refresh();
        });
    }

    /** Head: "Review okay?" = Yes -> Treasury. */
    public function approveApv(CashAdvanceLiquidation $liq, User $actor, ?string $comments = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_APV_REVIEW]);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $this->transition($liq, $actor, LiquidationStatus::FOR_REIMBURSEMENT, ApprovalStatus::APPROVED, $comments, 'ACCT_HEAD');
            $this->notifyRole('TREASURY', $liq, 'Reimbursement to process', "{$liq->control_no} APV was approved — process the reimbursement.");

            return $liq->refresh();
        });
    }

    /** Head: "Review okay?" = No -> back to Accounting Staff verification (per the flow). */
    public function rejectApv(CashAdvanceLiquidation $liq, User $actor, string $comments): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_APV_REVIEW]);

        return DB::transaction(function () use ($liq, $actor, $comments) {
            $this->transition($liq, $actor, LiquidationStatus::FOR_STAFF_VERIFICATION, ApprovalStatus::NOT_APPROVED, $comments, 'ACCT_HEAD');
            $this->notifyRole('ACCT_STAFF', $liq, 'APV not approved', "{$liq->control_no} APV was sent back: {$comments}");

            return $liq->refresh();
        });
    }

    /** Treasury: Process reimbursement -> Input check voucher -> Notify Requestor -> End. */
    public function processReimbursement(CashAdvanceLiquidation $liq, User $actor, string $cvNo, ?string $paymentMode = null, ?string $paymentReference = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_REIMBURSEMENT]);

        return DB::transaction(function () use ($liq, $actor, $cvNo, $paymentMode, $paymentReference) {
            $liq->voucher->update([
                'cv_no' => $cvNo,
                'cv_date' => now()->toDateString(),
                'payment_mode' => $paymentMode,
                'payment_reference' => $paymentReference,
                'reimbursed_at' => now(),
                'processed_by' => $actor->id,
            ]);

            $liq->closed_at = now();
            $this->transition($liq, $actor, LiquidationStatus::CLOSED, ApprovalStatus::REIMBURSED, null, 'TREASURY');

            $this->notifyUser($liq->prepared_by, $liq, 'Liquidation reimbursed',
                "{$liq->control_no} was reimbursed (CV {$cvNo}). The liquidation is closed.");

            return $liq->refresh();
        });
    }

    /** Head: nothing payable -> Process liquidation and tag as close. */
    public function closeLiquidation(CashAdvanceLiquidation $liq, User $actor, ?string $cashReturnReference = null, ?string $comments = null): CashAdvanceLiquidation
    {
        $this->assertStatus($liq, [LiquidationStatus::FOR_HEAD_CLOSURE]);

        if ((float) $liq->amount_to_return > 0 && blank($cashReturnReference)) {
            throw self::fail('cash_return_reference',
                'Unspent cash of '.number_format($liq->amount_to_return, 2).' must be returned — enter the receipt/reference of the returned cash.');
        }

        return DB::transaction(function () use ($liq, $actor, $cashReturnReference, $comments) {
            $liq->cash_return_reference = $cashReturnReference;
            $liq->closed_at = now();
            $this->transition($liq, $actor, LiquidationStatus::CLOSED, ApprovalStatus::CLOSED, $comments, 'ACCT_HEAD');

            $this->notifyUser($liq->prepared_by, $liq, 'Liquidation closed', "{$liq->control_no} has been processed and closed.");

            return $liq->refresh();
        });
    }

    // ---- Questionable Expense tagging ---------------------------------

    /**
     * Offered where the flow asks "Liquidation okay?" (Approver/OE) and
     * "Verification okay?" (Accounting Staff). The item stops counting as
     * liquidated (it stays Advances to Employee) and a Questionable Expense
     * starts at "waiting for hard copy". The liquidation itself carries on.
     */
    public function tagQuestionable(LiquidationItem $item, User $actor, string $reason): QuestionableExpense
    {
        $liq = $item->liquidation()->with(['status', 'fundRequest'])->firstOrFail();

        $roleCode = match ($liq->status->code) {
            LiquidationStatus::FOR_OE_VALIDATION => 'OE',
            LiquidationStatus::FOR_STAFF_VERIFICATION => 'ACCT_STAFF',
            default => throw self::fail('status', 'Items can only be tagged while the liquidation is with the Approver/OE or Accounting Staff.'),
        };

        if ($roleCode === 'OE') {
            $this->assertOeScope($liq->fundRequest, $actor);
        }

        if ($item->status->code !== LiquidationItemStatus::EXPENSE) {
            throw self::fail('item', 'This item is already tagged.');
        }

        return DB::transaction(function () use ($item, $liq, $actor, $reason, $roleCode) {
            $waiting = QuestionableExpenseStatus::where('code', QuestionableExpenseStatus::WAITING_HARD_COPY)->firstOrFail();

            $qe = QuestionableExpense::create([
                'control_no' => $this->controlNo->nextQuestionableExpense(),
                'liquidation_item_id' => $item->id,
                'status_id' => $waiting->id,
                'tagged_by' => $actor->id,
                'tagged_role' => $roleCode,
                'tagged_at' => now(),
                'reason' => $reason,
            ]);

            $item->update(['liquidation_item_status_id' => LiquidationItemStatus::where('code', LiquidationItemStatus::QUESTIONABLE)->firstOrFail()->id]);
            $liq->recalculateTotals();

            $qe->logEvent($actor, $roleCode, 'Tagged as Questionable Expense (waiting for hard copy)', null, $waiting->id, $reason);
            $this->logAction($liq, $actor, $roleCode, ApprovalStatus::QE_TAGGED,
                "{$qe->control_no}: \"{$item->purpose}\" — {$reason}");

            $this->notifyRole('ACCT_STAFF', $qe, 'Questionable expense tagged',
                "{$qe->control_no} ({$liq->control_no}) is waiting for the hard copy.");

            return $qe;
        });
    }

    /** Take the tag back — only the tagger, only while the hard copy hasn't been checked and the liquidation is still at that stage. */
    public function untagQuestionable(LiquidationItem $item, User $actor): void
    {
        $liq = $item->liquidation()->with('status')->firstOrFail();
        $qe = $item->questionableExpense()->with('status')->first();

        if (! $qe) {
            throw self::fail('item', 'This item is not tagged.');
        }
        if (! in_array($liq->status->code, [LiquidationStatus::FOR_OE_VALIDATION, LiquidationStatus::FOR_STAFF_VERIFICATION], true)
            || $qe->status->code !== QuestionableExpenseStatus::WAITING_HARD_COPY
            || $qe->tagged_by !== $actor->id) {
            throw self::fail('item', 'This tag can no longer be removed.');
        }

        DB::transaction(function () use ($item, $liq, $qe, $actor) {
            $roleCode = $qe->tagged_role;
            $controlNo = $qe->control_no;

            $item->update(['liquidation_item_status_id' => LiquidationItemStatus::where('code', LiquidationItemStatus::EXPENSE)->firstOrFail()->id]);
            $qe->delete(); // its events cascade; nothing else exists yet at this stage
            $liq->recalculateTotals();

            $this->logAction($liq, $actor, $roleCode, ApprovalStatus::QE_UNTAGGED, "{$controlNo} removed from \"{$item->purpose}\".");
        });
    }

    // ---- internals ----------------------------------------------------

    private function transition(CashAdvanceLiquidation $liq, User $actor, string $toStatusCode, string $approvalCode, ?string $comments, string $roleCode): void
    {
        $from = $liq->status;
        $to = LiquidationStatus::where('code', $toStatusCode)->firstOrFail();

        $liq->liquidation_status_id = $to->id;
        $liq->save();
        $liq->load('status');

        LiquidationApproval::create([
            'liquidation_id' => $liq->id,
            'approver_id' => $actor->id,
            'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
            'approval_status_id' => ApprovalStatus::where('code', $approvalCode)->firstOrFail()->id,
            'from_liquidation_status_id' => $from?->id,
            'to_liquidation_status_id' => $to->id,
            'approval_date' => now(),
            'comments' => $comments,
        ]);
    }

    /** History row for an action that doesn't move the liquidation (from = to). */
    private function logAction(CashAdvanceLiquidation $liq, User $actor, string $roleCode, string $approvalCode, ?string $comments): void
    {
        LiquidationApproval::create([
            'liquidation_id' => $liq->id,
            'approver_id' => $actor->id,
            'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
            'approval_status_id' => ApprovalStatus::where('code', $approvalCode)->firstOrFail()->id,
            'from_liquidation_status_id' => $liq->liquidation_status_id,
            'to_liquidation_status_id' => $liq->liquidation_status_id,
            'approval_date' => now(),
            'comments' => $comments,
        ]);
    }

    private function assertStatus(CashAdvanceLiquidation $liq, array $allowed): void
    {
        if (! in_array($liq->status->code, $allowed, true)) {
            throw self::fail('status', "This action isn't valid from the liquidation's current status ({$liq->status->label}).");
        }
    }

    private function assertOwner(CashAdvanceLiquidation $liq, User $actor): void
    {
        if ($liq->prepared_by !== $actor->id) {
            throw self::fail('requestor', 'Only the Requestor who prepared this liquidation may act on it.');
        }
    }
}
