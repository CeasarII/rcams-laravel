<?php

namespace App\Services;

use App\Models\FundRequestType;
use Illuminate\Support\Facades\DB;

/**
 * Generates CA-2026-00001 / RF-2026-00001 style control numbers.
 * Confirm this format against your actual numbering convention (Phase 1 §8 Q11)
 * before going live — this is a placeholder pattern.
 *
 * Must be called from inside the same DB transaction that inserts the
 * fund_requests row, so the counter and the row commit or roll back together.
 */
class ControlNoGenerator
{
    public function next(string $fundRequestTypeCode): string
    {
        $prefix = match ($fundRequestTypeCode) {
            FundRequestType::CASH_ADVANCE => 'CA',
            FundRequestType::REVOLVING_FUND => 'RF',
            default => 'FR',
        };

        $year = now()->year;

        // SQL Server: UPDLOCK + HOLDLOCK on the row range prevents two
        // concurrent submits from getting the same sequence number.
        $count = DB::table('fund_requests')
            ->when(DB::connection()->getDriverName() === 'sqlsrv', fn ($q) => $q->lock('with (updlock, holdlock)'))
            ->where('control_no', 'like', "{$prefix}-{$year}-%")
            ->count();

        $next = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$next}";
    }

    /** LQ-2026-00001 — Cash Advance Liquidation. */
    public function nextLiquidation(): string
    {
        return $this->sequence('LQ', 'cash_advance_liquidations');
    }

    /** QE-2026-00001 — Questionable Expense. */
    public function nextQuestionableExpense(): string
    {
        return $this->sequence('QE', 'questionable_expenses');
    }

    /** RFE-2026-00001 — Revolving Fund replenishment (voyage expense details). */
    public function nextRevolvingFundExpense(): string
    {
        return $this->sequence('RFE', 'revolving_fund_expenses');
    }

    private function sequence(string $prefix, string $table): string
    {
        $year = now()->year;

        $count = DB::table($table)
            ->when(DB::connection()->getDriverName() === 'sqlsrv', fn ($q) => $q->lock('with (updlock, holdlock)'))
            ->where('control_no', 'like', "{$prefix}-{$year}-%")
            ->count();

        return "{$prefix}-{$year}-".str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
