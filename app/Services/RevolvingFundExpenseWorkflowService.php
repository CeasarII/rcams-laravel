<?php

namespace App\Services;

use App\Models\ApprovalStatus;
use App\Models\LiquidationItemStatus;
use App\Models\QuestionableExpense;
use App\Models\QuestionableExpenseStatus;
use App\Models\RevolvingFundExpense;
use App\Models\RevolvingFundExpenseApproval;
use App\Models\RevolvingFundExpenseItem;
use App\Models\RevolvingFundExpenseStatus;
use App\Models\RevolvingFundTransactionType;
use App\Models\RevolvingFundVoucher;
use App\Models\RevolvingFundVoyage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revolving Fund Replenishment (process guide, Process B):
 *
 *  1 Requestor   Prepare and submit voyage expense details
 *  2 OE          Verify and validate (soft copy)       Not valid -> Requestor revises details and tag
 *  3 Staff       Validate replenishment details        Not valid -> OE revises details and request, validates again
 *  4 Head        Review and decide                     Not approved -> back to Staff (step 3)
 *  5 Staff       Create the AP voucher
 *  6 Head        Set the tag status: HC pending        (only when items are tagged)
 *  7 Treasury    Create the CV number
 *  8 Treasury    Disburse
 *  9 Treasury    Notify the Requestor -> Completed
 *
 * Questionable items (tagged by the OE at step 2 or Staff at step 3) still move
 * on to approval and the voucher — the tag rides along as "Advances to
 * Employee, HC pending" and the Questionable Expense process takes over once
 * the Head sets HC pending (step 6).
 *
 * Money: the Head's approval posts the voyage expenses to the fund ledger as
 * EXPENSE (-), and Treasury's disbursement posts REPLENISHMENT (+).
 */
class RevolvingFundExpenseWorkflowService
{
    use Concerns\NotifiesUsers;

    public function __construct(private ControlNoGenerator $controlNo, private RevolvingFundLedger $ledger)
    {
    }

    /** Step 1 (start): only the fund's custodian, on an open voyage of an active fund. */
    public function create(RevolvingFundVoyage $voyage, User $actor, array $data = []): RevolvingFundExpense
    {
        $voyage->loadMissing('revolvingFund.status', 'status');
        $fund = $voyage->revolvingFund;

        if ($fund->custodian_id !== $actor->id) {
            throw self::fail('fund', 'Only the custodian of this revolving fund can prepare its voyage expenses.');
        }
        if (! $fund->isActive()) {
            throw self::fail('fund', 'This revolving fund is not active.');
        }
        if (! $voyage->isOpen()) {
            throw self::fail('voyage', 'Voyage expenses can only be prepared for an open voyage.');
        }

        return DB::transaction(fn () => RevolvingFundExpense::create([
            'control_no' => $this->controlNo->nextRevolvingFundExpense(),
            'voyage_id' => $voyage->id,
            'prepared_by' => $actor->id,
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'remarks' => $data['remarks'] ?? null,
            'status_id' => $this->statusId(RevolvingFundExpenseStatus::DRAFT),
        ])->refresh());
    }

    /** Step 1 (submit / resubmit after "Revise expense details and tag"). */
    public function submit(RevolvingFundExpense $expense, User $actor): RevolvingFundExpense
    {
        $this->assertStatus($expense, RevolvingFundExpenseStatus::REQUESTOR_EDITABLE);
        $this->assertOwner($expense, $actor);

        return DB::transaction(function () use ($expense, $actor) {
            $items = $expense->items()->withCount('attachments')->get();

            if ($items->isEmpty()) {
                throw self::fail('items', 'Add at least one expense item before submitting.');
            }

            $unsupported = $items->where('attachments_count', 0);
            if ($unsupported->isNotEmpty()) {
                throw self::fail('attachments', 'Every item needs a supporting document (soft copy). Missing on: '
                    .$unsupported->pluck('purpose')->take(3)->implode(', ').($unsupported->count() > 3 ? '…' : '.'));
            }

            $expense->recalculateTotal();
            $first = is_null($expense->submitted_at);

            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_OE_VALIDATION, ApprovalStatus::SUBMITTED, null, 'REQUESTOR');

            if ($first) {
                $expense->submitted_at = now();
                $expense->save();
            }

            $this->notifyOe($expense->fund(), $expense, 'Voyage expenses for validation', "{$expense->control_no} is ready for OE validation.");

            return $expense->refresh();
        });
    }

    /** Step 2 — valid. Also used by the OE after revising a request Staff sent back. */
    public function validateByOe(RevolvingFundExpense $expense, User $actor, ?string $comments = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_OE_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_OE]);
        $this->assertOeScope($expense->fund(), $actor);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_STAFF_VALIDATION, ApprovalStatus::VALIDATED, $comments, 'OE');
            $this->notifyRole('ACCT_STAFF', $expense, 'Replenishment for validation', "{$expense->control_no} is ready for Accounting validation.");

            return $expense->refresh();
        });
    }

    /** Step 2 — not valid: back to the Requestor to revise the details and tag. */
    public function returnToRequestor(RevolvingFundExpense $expense, User $actor, string $comments): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_OE_VALIDATION]);
        $this->assertOeScope($expense->fund(), $actor);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::RETURNED_TO_REQUESTOR, ApprovalStatus::RETURNED, $comments, 'OE');
            $this->notifyUser($expense->prepared_by, $expense, 'Voyage expenses returned', "{$expense->control_no} was returned: {$comments}");

            return $expense->refresh();
        });
    }

    /** Step 3 — valid. */
    public function validateByStaff(RevolvingFundExpense $expense, User $actor, ?string $comments = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_STAFF_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_STAFF]);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_HEAD_APPROVAL, ApprovalStatus::VALIDATED, $comments, 'ACCT_STAFF');
            $this->notifyRole('ACCT_HEAD', $expense, 'Replenishment for approval', "{$expense->control_no} is ready for your review.");

            return $expense->refresh();
        });
    }

    /** Step 3 — not valid: the OE revises the details and request, then validates again. */
    public function returnToOe(RevolvingFundExpense $expense, User $actor, string $comments): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_STAFF_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_STAFF]);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::RETURNED_TO_OE, ApprovalStatus::RETURNED, $comments, 'ACCT_STAFF');
            $this->notifyOe($expense->fund(), $expense, 'Replenishment returned to OE', "{$expense->control_no} was returned: {$comments}");

            return $expense->refresh();
        });
    }

    /** Step 4 — approved. The voyage expenses are now posted against the fund. */
    public function approve(RevolvingFundExpense $expense, User $actor, ?string $comments = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_HEAD_APPROVAL]);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->ledger->post($expense->fund(), RevolvingFundTransactionType::EXPENSE, $expense->total_amount, [
                'expense_id' => $expense->id,
                'reference_no' => $expense->control_no,
                'description' => "Voyage expenses {$expense->voyage->voyage_no}",
                'posted_by' => $actor->id,
            ]);

            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_AP_VOUCHER, ApprovalStatus::APPROVED, $comments, 'ACCT_HEAD');
            $this->notifyRole('ACCT_STAFF', $expense, 'Replenishment approved', "{$expense->control_no} was approved — create the AP voucher.");

            return $expense->refresh();
        });
    }

    /** Step 4 — not approved: back to Accounting Staff (step 3). */
    public function notApprove(RevolvingFundExpense $expense, User $actor, string $comments): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_HEAD_APPROVAL]);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::RETURNED_TO_STAFF, ApprovalStatus::NOT_APPROVED, $comments, 'ACCT_HEAD');
            $this->notifyRole('ACCT_STAFF', $expense, 'Replenishment not approved', "{$expense->control_no} was sent back: {$comments}");

            return $expense->refresh();
        });
    }

    /** Step 5. With tagged items the Head sets their status next (step 6); otherwise straight to Treasury. */
    public function createApVoucher(RevolvingFundExpense $expense, User $actor, string $apvNo, ?string $apvDate = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_AP_VOUCHER]);

        return DB::transaction(function () use ($expense, $actor, $apvNo, $apvDate) {
            RevolvingFundVoucher::create([
                'expense_id' => $expense->id,
                'apv_no' => $apvNo,
                'apv_date' => $apvDate ?? now()->toDateString(),
                'amount' => $expense->total_amount,
                'prepared_by' => $actor->id,
            ]);

            $hasTags = $this->taggedQuestionables($expense)->isNotEmpty();

            $this->transition(
                $expense, $actor,
                $hasTags ? RevolvingFundExpenseStatus::FOR_HC_TAG : RevolvingFundExpenseStatus::FOR_CV_NUMBER,
                ApprovalStatus::APV_CREATED, null, 'ACCT_STAFF'
            );

            $hasTags
                ? $this->notifyRole('ACCT_HEAD', $expense, 'Set the HC pending tag', "{$expense->control_no} has questionable items — set their tag status to HC pending.")
                : $this->notifyRole('TREASURY', $expense, 'AP voucher created', "{$expense->control_no} is ready for a CV number.");

            return $expense->refresh();
        });
    }

    /** Step 6 — the Head sets the tag status of every flagged item: Advances to Employee, HC pending. */
    public function setHcPending(RevolvingFundExpense $expense, User $actor, ?string $comments = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_HC_TAG]);

        return DB::transaction(function () use ($expense, $actor, $comments) {
            $waiting = QuestionableExpenseStatus::where('code', QuestionableExpenseStatus::WAITING_HARD_COPY)->firstOrFail();

            foreach ($this->taggedQuestionables($expense) as $qe) {
                $from = $qe->status_id;
                $qe->update(['status_id' => $waiting->id]);
                $qe->logEvent($actor, 'ACCT_HEAD', 'Tag status set: Advances to Employee, HC pending', $from, $waiting->id, $comments);
                $this->notifyRole('ACCT_STAFF', $qe, 'Questionable expense waiting for hard copy', "{$qe->control_no} ({$expense->control_no}) is waiting for the hard copy.");
            }

            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_CV_NUMBER, ApprovalStatus::HC_PENDING_SET, $comments, 'ACCT_HEAD');
            $this->notifyRole('TREASURY', $expense, 'AP voucher created', "{$expense->control_no} is ready for a CV number.");

            return $expense->refresh();
        });
    }

    /** Step 7. */
    public function provideCvNumber(RevolvingFundExpense $expense, User $actor, string $cvNo): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_CV_NUMBER]);

        return DB::transaction(function () use ($expense, $actor, $cvNo) {
            $expense->voucher->update(['cv_no' => $cvNo, 'cv_date' => now()->toDateString()]);

            $this->transition($expense, $actor, RevolvingFundExpenseStatus::FOR_DISBURSEMENT, ApprovalStatus::CV_CREATED, null, 'TREASURY');

            return $expense->refresh();
        });
    }

    /** Steps 8-9: disburse, replenish the fund, notify the Requestor. */
    public function disburse(RevolvingFundExpense $expense, User $actor, ?string $paymentMode = null, ?string $paymentReference = null): RevolvingFundExpense
    {
        $this->assertStatus($expense, [RevolvingFundExpenseStatus::FOR_DISBURSEMENT]);

        return DB::transaction(function () use ($expense, $actor, $paymentMode, $paymentReference) {
            $voucher = $expense->voucher;
            $voucher->update([
                'payment_mode' => $paymentMode,
                'payment_reference' => $paymentReference,
                'disbursed_at' => now(),
                'processed_by' => $actor->id,
            ]);

            $this->ledger->post($expense->fund(), RevolvingFundTransactionType::REPLENISHMENT, $voucher->amount, [
                'expense_id' => $expense->id,
                'reference_no' => $voucher->cv_no,
                'description' => "Replenishment {$expense->control_no}",
                'posted_by' => $actor->id,
            ]);

            $expense->completed_at = now();
            $this->transition($expense, $actor, RevolvingFundExpenseStatus::COMPLETED, ApprovalStatus::DISBURSED, null, 'TREASURY');

            $this->notifyUser($expense->prepared_by, $expense, 'Replenishment disbursed', "{$expense->control_no} has been disbursed. The revolving fund was replenished.");

            return $expense->refresh();
        });
    }

    // ---- Questionable Expense tagging (steps 2 and 3) -----------------

    /**
     * The OE (step 2) or Accounting Staff (step 3) flags an item: it is tagged
     * Advances to Employee, HC pending. The replenishment carries on; the QE
     * itself starts moving once the Head sets HC pending (step 6).
     */
    public function tagQuestionable(RevolvingFundExpenseItem $item, User $actor, string $reason): QuestionableExpense
    {
        $expense = $item->expense()->with(['status', 'voyage.revolvingFund'])->firstOrFail();

        $roleCode = match ($expense->status->code) {
            RevolvingFundExpenseStatus::FOR_OE_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_OE => 'OE',
            RevolvingFundExpenseStatus::FOR_STAFF_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_STAFF => 'ACCT_STAFF',
            default => throw self::fail('status', 'Items can only be tagged while the expenses are with the Approver/OE or Accounting Staff.'),
        };

        if ($roleCode === 'OE') {
            $this->assertOeScope($expense->voyage->revolvingFund, $actor);
        }

        if ($item->status->code !== LiquidationItemStatus::EXPENSE) {
            throw self::fail('item', 'This item is already tagged.');
        }

        return DB::transaction(function () use ($item, $expense, $actor, $reason, $roleCode) {
            $tagged = QuestionableExpenseStatus::where('code', QuestionableExpenseStatus::TAGGED)->firstOrFail();

            $qe = QuestionableExpense::create([
                'control_no' => $this->controlNo->nextQuestionableExpense(),
                'revolving_fund_expense_item_id' => $item->id,
                'status_id' => $tagged->id,
                'tagged_by' => $actor->id,
                'tagged_role' => $roleCode,
                'tagged_at' => now(),
                'reason' => $reason,
            ]);

            $item->update(['liquidation_item_status_id' => $this->itemStatusId(LiquidationItemStatus::QUESTIONABLE)]);

            $qe->logEvent($actor, $roleCode, 'Tagged: Advances to Employee, HC pending', null, $tagged->id, $reason);
            $this->logAction($expense, $actor, $roleCode, ApprovalStatus::QE_TAGGED, "{$qe->control_no}: \"{$item->purpose}\" — {$reason}");

            return $qe;
        });
    }

    /** Only the tagger, only while still in the tagging stage and before the Head sets HC pending. */
    public function untagQuestionable(RevolvingFundExpenseItem $item, User $actor): void
    {
        $expense = $item->expense()->with('status')->firstOrFail();
        $qe = $item->questionableExpense()->with('status')->first();

        if (! $qe) {
            throw self::fail('item', 'This item is not tagged.');
        }

        $taggingStage = [
            RevolvingFundExpenseStatus::FOR_OE_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_OE,
            RevolvingFundExpenseStatus::FOR_STAFF_VALIDATION, RevolvingFundExpenseStatus::RETURNED_TO_STAFF,
        ];

        if (! in_array($expense->status->code, $taggingStage, true)
            || $qe->status->code !== QuestionableExpenseStatus::TAGGED
            || $qe->tagged_by !== $actor->id) {
            throw self::fail('item', 'This tag can no longer be removed.');
        }

        DB::transaction(function () use ($item, $expense, $qe, $actor) {
            $roleCode = $qe->tagged_role;
            $controlNo = $qe->control_no;

            $item->update(['liquidation_item_status_id' => $this->itemStatusId(LiquidationItemStatus::EXPENSE)]);
            $qe->delete();

            $this->logAction($expense, $actor, $roleCode, ApprovalStatus::QE_UNTAGGED, "{$controlNo} removed from \"{$item->purpose}\".");
        });
    }

    // ---- internals ----------------------------------------------------

    private function taggedQuestionables(RevolvingFundExpense $expense)
    {
        return QuestionableExpense::query()
            ->whereIn('revolving_fund_expense_item_id', $expense->items()->pluck('id'))
            ->whereHas('status', fn ($q) => $q->where('code', QuestionableExpenseStatus::TAGGED))
            ->get();
    }

    private function transition(RevolvingFundExpense $expense, User $actor, string $toStatusCode, string $approvalCode, ?string $comments, string $roleCode): void
    {
        $from = $expense->status;
        $to = RevolvingFundExpenseStatus::where('code', $toStatusCode)->firstOrFail();

        $expense->status_id = $to->id;
        $expense->save();
        $expense->load('status');

        $this->record($expense, $actor, $roleCode, $approvalCode, $from?->id, $to->id, $comments);
    }

    /** History row for an action that doesn't move the expense (from = to). */
    private function logAction(RevolvingFundExpense $expense, User $actor, string $roleCode, string $approvalCode, ?string $comments): void
    {
        $this->record($expense, $actor, $roleCode, $approvalCode, $expense->status_id, $expense->status_id, $comments);
    }

    private function record(RevolvingFundExpense $expense, User $actor, string $roleCode, string $approvalCode, ?int $fromId, int $toId, ?string $comments): void
    {
        RevolvingFundExpenseApproval::create([
            'expense_id' => $expense->id,
            'approver_id' => $actor->id,
            'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
            'approval_status_id' => ApprovalStatus::where('code', $approvalCode)->firstOrFail()->id,
            'from_status_id' => $fromId,
            'to_status_id' => $toId,
            'approval_date' => now(),
            'comments' => $comments,
        ]);
    }

    private function statusId(string $code): int
    {
        return RevolvingFundExpenseStatus::where('code', $code)->firstOrFail()->id;
    }

    private function itemStatusId(string $code): int
    {
        return LiquidationItemStatus::where('code', $code)->firstOrFail()->id;
    }

    private function assertStatus(RevolvingFundExpense $expense, array $allowed): void
    {
        if (! in_array($expense->status->code, $allowed, true)) {
            throw self::fail('status', "This action isn't valid from the current status ({$expense->status->label}).");
        }
    }

    private function assertOwner(RevolvingFundExpense $expense, User $actor): void
    {
        if ($expense->prepared_by !== $actor->id) {
            throw self::fail('requestor', 'Only the Requestor who prepared these expenses may act on them.');
        }
    }
}
