<?php

namespace App\Policies;

use App\Models\RevolvingFundExpense;
use App\Models\RevolvingFundExpenseStatus as S;
use App\Models\User;
use App\Support\LiquidationAccess;

/**
 * Process B: who may do what, at which status. OE scoping follows the fund's
 * vessel (Sea Base) or department (Shore Base), and the employee who prepared
 * the expenses never acts on them in a review role.
 */
class RevolvingFundExpensePolicy
{
    public function view(User $user, RevolvingFundExpense $expense): bool
    {
        return LiquidationAccess::seesAll(LiquidationAccess::roles($user)) || $expense->prepared_by === $user->id;
    }

    /** Submit, and revise as the Requestor — Draft or sent back to them. */
    public function submit(User $user, RevolvingFundExpense $expense): bool
    {
        return $expense->prepared_by === $user->id && $this->in($expense, S::REQUESTOR_EDITABLE);
    }

    /**
     * Editing the content: the Requestor while Draft/Returned, and the OE when
     * Accounting sent it back to them ("OE revises the details and request").
     */
    public function update(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->submit($user, $expense)
            || ($this->oe($user, $expense) && $this->in($expense, [S::RETURNED_TO_OE]));
    }

    /** Step 2, valid — also after revising what Staff returned. */
    public function validateAsOe(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->oe($user, $expense) && $this->in($expense, [S::FOR_OE_VALIDATION, S::RETURNED_TO_OE]);
    }

    /** Step 2, not valid -> Requestor. */
    public function returnToRequestor(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->oe($user, $expense) && $this->in($expense, [S::FOR_OE_VALIDATION]);
    }

    /** Step 3, valid or not valid. */
    public function validateAsStaff(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'ACCT_STAFF') && $this->in($expense, [S::FOR_STAFF_VALIDATION, S::RETURNED_TO_STAFF]);
    }

    /** Step 4. */
    public function decide(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'ACCT_HEAD') && $this->in($expense, [S::FOR_HEAD_APPROVAL]);
    }

    /** Step 5. */
    public function createApv(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'ACCT_STAFF') && $this->in($expense, [S::FOR_AP_VOUCHER]);
    }

    /** Step 6. */
    public function setHcPending(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'ACCT_HEAD') && $this->in($expense, [S::FOR_HC_TAG]);
    }

    /** Step 7. */
    public function provideCv(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'TREASURY') && $this->in($expense, [S::FOR_CV_NUMBER]);
    }

    /** Steps 8-9. */
    public function disburse(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->role($user, $expense, 'TREASURY') && $this->in($expense, [S::FOR_DISBURSEMENT]);
    }

    /** Flag items as questionable: the OE at step 2, Accounting Staff at step 3. */
    public function tagQuestionable(User $user, RevolvingFundExpense $expense): bool
    {
        return $this->validateAsOe($user, $expense) || $this->validateAsStaff($user, $expense);
    }

    private function role(User $user, RevolvingFundExpense $expense, string $code): bool
    {
        return $user->hasRole($code) && $expense->prepared_by !== $user->id;
    }

    private function oe(User $user, RevolvingFundExpense $expense): bool
    {
        $fund = $expense->voyage->revolvingFund;

        return $this->role($user, $expense, 'OE')
            && ($fund->vessel_id !== null
                ? $user->vessels()->where('vessels.id', $fund->vessel_id)->exists()
                : $user->departments()->where('departments.id', $fund->department_id)->exists());
    }

    private function in(RevolvingFundExpense $expense, array $codes): bool
    {
        return in_array($expense->status->code, $codes, true);
    }
}
