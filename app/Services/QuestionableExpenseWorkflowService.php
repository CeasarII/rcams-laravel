<?php

namespace App\Services;

use App\Models\LiquidationItemStatus;
use App\Models\QuestionableExpense;
use App\Models\QuestionableExpenseStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Questionable Expense (process guide, page 4). Starts when an item is tagged
 * (LiquidationWorkflowService::tagQuestionable) at "waiting for hard copy".
 *
 *  1 Staff   Validate the file, now with hard copy
 *              Valid     -> change the tag to Expense -> 7 close
 *              Not valid -> 2 keep the tag as Advances to Employee
 *  3 Staff   Generate the Incident Report (IR)
 *  4 OE      Receive the IR
 *  5 Crewing Subject it to salary deduction
 *  6 Crewing Secure proof of deduction (authority to deduct)
 *  7 Staff   Close the QE
 *
 * The liquidation keeps going meanwhile. Closing a QE re-tags its item
 * (Expense, or settled by salary deduction); the liquidation's totals follow
 * only while it is still before/at verification — see recalculateTotals().
 */
class QuestionableExpenseWorkflowService
{
    use Concerns\NotifiesUsers;

    /** Step 1. Valid closes the QE at once (step 7 is just closing); not valid carries on to the IR. */
    public function validateHardCopy(QuestionableExpense $qe, User $actor, bool $valid, string $receivedOn, ?string $remarks = null): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::WAITING_HARD_COPY]);

        return DB::transaction(function () use ($qe, $actor, $valid, $receivedOn, $remarks) {
            $from = $qe->status_id;

            $qe->fill([
                'hard_copy_received_on' => $receivedOn,
                'hard_copy_valid' => $valid,
                'hard_copy_checked_by' => $actor->id,
                'hard_copy_checked_at' => now(),
                'hard_copy_remarks' => $remarks,
            ]);

            if ($valid) {
                $closed = $this->statusId(QuestionableExpenseStatus::CLOSED);
                $qe->fill([
                    'status_id' => $closed,
                    'resolution' => QuestionableExpense::RESOLUTION_EXPENSE,
                    'closed_by' => $actor->id,
                    'closed_at' => now(),
                ])->save();

                $this->retagItem($qe, LiquidationItemStatus::EXPENSE);
                $qe->logEvent($actor, 'ACCT_STAFF', 'Hard copy valid — tag changed to Expense, QE closed', $from, $closed, $remarks);

                $this->notifyUser($qe->ownerId(), $qe, 'Questionable expense cleared',
                    "{$qe->control_no} was cleared — the item is now a normal expense.");
            } else {
                $to = $this->statusId(QuestionableExpenseStatus::FOR_IR);
                $qe->fill(['status_id' => $to])->save();
                $qe->logEvent($actor, 'ACCT_STAFF', 'Hard copy not valid — tag kept as Advances to Employee', $from, $to, $remarks);
            }

            return $qe->refresh();
        });
    }

    /** Steps 2-3. */
    public function generateIr(QuestionableExpense $qe, User $actor, string $irNo, string $irDate): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::FOR_IR]);

        return DB::transaction(function () use ($qe, $actor, $irNo, $irDate) {
            $from = $qe->status_id;
            $to = $this->statusId(QuestionableExpenseStatus::FOR_OE_RECEIPT);

            $qe->fill(['ir_no' => $irNo, 'ir_date' => $irDate, 'ir_generated_by' => $actor->id, 'status_id' => $to])->save();
            $qe->logEvent($actor, 'ACCT_STAFF', "Incident Report {$irNo} generated", $from, $to);

            $this->notifyOe($qe->scopeModel(), $qe, 'Incident report to receive', "{$qe->control_no}: IR {$irNo} is ready for you to receive.");

            return $qe->refresh();
        });
    }

    /** Step 4 — by the OE scoped to the cash advance's vessel/department. */
    public function receiveIr(QuestionableExpense $qe, User $actor, ?string $remarks = null): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::FOR_OE_RECEIPT]);
        $this->assertOeScope($qe->scopeModel(), $actor);

        return DB::transaction(function () use ($qe, $actor, $remarks) {
            $from = $qe->status_id;
            $to = $this->statusId(QuestionableExpenseStatus::FOR_SALARY_DEDUCTION);

            $qe->fill(['ir_received_by' => $actor->id, 'ir_received_at' => now(), 'status_id' => $to])->save();
            $qe->logEvent($actor, 'OE', 'Incident Report received', $from, $to, $remarks);

            $this->notifyRole('CREWING', $qe, 'Salary deduction to process', "{$qe->control_no} is ready for salary deduction.");

            return $qe->refresh();
        });
    }

    /** Step 5. The deduction covers the whole item amount. */
    public function subjectToDeduction(QuestionableExpense $qe, User $actor, ?string $remarks = null): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::FOR_SALARY_DEDUCTION]);

        return DB::transaction(function () use ($qe, $actor, $remarks) {
            $from = $qe->status_id;
            $to = $this->statusId(QuestionableExpenseStatus::FOR_PROOF_OF_DEDUCTION);

            $qe->fill([
                'deduction_amount' => $qe->sourceItem()->gross_amount,
                'deduction_remarks' => $remarks,
                'deduction_by' => $actor->id,
                'deduction_at' => now(),
                'status_id' => $to,
            ])->save();
            $qe->logEvent($actor, 'CREWING', 'Subjected to salary deduction', $from, $to, $remarks);

            return $qe->refresh();
        });
    }

    /** Step 6 — needs the proof of deduction / authority to deduct uploaded first. */
    public function secureProof(QuestionableExpense $qe, User $actor, ?string $remarks = null): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::FOR_PROOF_OF_DEDUCTION]);

        if (! $qe->attachments()->exists()) {
            throw self::fail('attachments', 'Upload the proof of deduction (authority to deduct) first.');
        }

        return DB::transaction(function () use ($qe, $actor, $remarks) {
            $from = $qe->status_id;
            $to = $this->statusId(QuestionableExpenseStatus::FOR_CLOSURE);

            $qe->fill(['proof_secured_by' => $actor->id, 'proof_secured_at' => now(), 'status_id' => $to])->save();
            $qe->logEvent($actor, 'CREWING', 'Proof of deduction secured', $from, $to, $remarks);

            $this->notifyRole('ACCT_STAFF', $qe, 'Questionable expense ready to close', "{$qe->control_no} has its proof of deduction — close the QE.");

            return $qe->refresh();
        });
    }

    /** Step 7. The item is settled by salary deduction and stays out of the liquidated expenses. */
    public function close(QuestionableExpense $qe, User $actor, ?string $remarks = null): QuestionableExpense
    {
        $this->assertStatus($qe, [QuestionableExpenseStatus::FOR_CLOSURE]);

        return DB::transaction(function () use ($qe, $actor, $remarks) {
            $from = $qe->status_id;
            $to = $this->statusId(QuestionableExpenseStatus::CLOSED);

            $qe->fill([
                'status_id' => $to,
                'resolution' => QuestionableExpense::RESOLUTION_DEDUCTED,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ])->save();

            $this->retagItem($qe, LiquidationItemStatus::SETTLED_BY_DEDUCTION);
            $qe->logEvent($actor, 'ACCT_STAFF', 'QE closed — settled by salary deduction', $from, $to, $remarks);

            $this->notifyUser($qe->ownerId(), $qe, 'Questionable expense closed',
                "{$qe->control_no} was closed — settled by salary deduction.");

            return $qe->refresh();
        });
    }

    // ---- internals ----------------------------------------------------

    private function retagItem(QuestionableExpense $qe, string $itemStatusCode): void
    {
        $item = $qe->sourceItem();
        $item->update(['liquidation_item_status_id' => LiquidationItemStatus::where('code', $itemStatusCode)->firstOrFail()->id]);

        // A revolving fund replenishment already carried every item (questionable ones included),
        // so only a liquidation's figures follow its items.
        if (! $qe->isRevolvingFund()) {
            $liquidation = $item->liquidation;
            $liquidation->load('status');
            $liquidation->recalculateTotals(); // no-op once Accounting has verified (frozen)
        }
    }

    private function statusId(string $code): int
    {
        return QuestionableExpenseStatus::where('code', $code)->firstOrFail()->id;
    }

    private function assertStatus(QuestionableExpense $qe, array $allowed): void
    {
        if (! in_array($qe->status->code, $allowed, true)) {
            throw self::fail('status', "This action isn't valid from the current status ({$qe->status->label}).");
        }
    }
}
