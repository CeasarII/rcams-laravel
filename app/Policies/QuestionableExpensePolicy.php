<?php

namespace App\Policies;

use App\Models\QuestionableExpense;
use App\Models\QuestionableExpenseStatus;
use App\Models\User;
use App\Support\LiquidationAccess;

class QuestionableExpensePolicy
{
    public function view(User $user, QuestionableExpense $qe): bool
    {
        return LiquidationAccess::canViewQuestionableExpense($user, $qe);
    }

    /** 1 — Accounting Staff validates the file, now with hard copy. */
    public function validateHardCopy(User $user, QuestionableExpense $qe): bool
    {
        return $this->staff($user, $qe, [QuestionableExpenseStatus::WAITING_HARD_COPY]);
    }

    /** 2-3 — Staff generates the Incident Report. */
    public function generateIr(User $user, QuestionableExpense $qe): bool
    {
        return $this->staff($user, $qe, [QuestionableExpenseStatus::FOR_IR]);
    }

    /** 4 — Approver/OE (scoped to the cash advance) receives the IR. */
    public function receiveIr(User $user, QuestionableExpense $qe): bool
    {
        $request = $qe->scopeModel(); // the cash advance request, or the revolving fund

        return $user->hasRole('OE')
            && $this->notOwner($user, $qe)
            && ($request->vessel_id !== null
                ? $user->vessels()->where('vessels.id', $request->vessel_id)->exists()
                : $user->departments()->where('departments.id', $request->department_id)->exists())
            && $this->in($qe, [QuestionableExpenseStatus::FOR_OE_RECEIPT]);
    }

    /** 5 — Crewing subjects it to salary deduction. */
    public function subjectToDeduction(User $user, QuestionableExpense $qe): bool
    {
        return $this->crewing($user, $qe, [QuestionableExpenseStatus::FOR_SALARY_DEDUCTION]);
    }

    /** 6 — Crewing secures proof of deduction (uploads the authority to deduct, then confirms). */
    public function secureProof(User $user, QuestionableExpense $qe): bool
    {
        return $this->crewing($user, $qe, [QuestionableExpenseStatus::FOR_PROOF_OF_DEDUCTION]);
    }

    /** 7 — Staff closes the QE. */
    public function close(User $user, QuestionableExpense $qe): bool
    {
        return $this->staff($user, $qe, [QuestionableExpenseStatus::FOR_CLOSURE]);
    }

    private function staff(User $user, QuestionableExpense $qe, array $codes): bool
    {
        return $user->hasRole('ACCT_STAFF') && $this->notOwner($user, $qe) && $this->in($qe, $codes);
    }

    private function crewing(User $user, QuestionableExpense $qe, array $codes): bool
    {
        return $user->hasRole('CREWING') && $this->notOwner($user, $qe) && $this->in($qe, $codes);
    }

    /** The employee whose expense it is never works their own QE. */
    private function notOwner(User $user, QuestionableExpense $qe): bool
    {
        return $qe->ownerId() !== $user->id;
    }

    private function in(QuestionableExpense $qe, array $codes): bool
    {
        return in_array($qe->status->code, $codes, true);
    }
}
