<?php

namespace App\Services;

use App\Models\RevolvingFund;
use App\Models\RevolvingFundTransaction;
use App\Models\RevolvingFundTransactionType;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that moves a revolving fund's balance. Every change is one
 * ledger row (type + positive amount + resulting balance), written together
 * with the new RevolvingFund::current_balance under a row lock so concurrent
 * postings can't lose an update.
 *
 *   FUNDING        +  a Revolving Fund request is disbursed (or an opening balance)
 *   EXPENSE        -  a replenishment is approved: the voyage expenses are now an obligation
 *   REPLENISHMENT  +  that replenishment is disbursed — the fund is made whole
 *
 * The balance may go negative between EXPENSE and REPLENISHMENT: the money was
 * already spent, the books just haven't caught up.
 */
class RevolvingFundLedger
{
    /**
     * @param  array{fund_request_id?: int, expense_id?: int, reference_no?: string, description?: string, posted_by?: int, transaction_date?: string}  $meta
     */
    public function post(RevolvingFund $fund, string $typeCode, float|int|string $amount, array $meta = []): RevolvingFundTransaction
    {
        return DB::transaction(function () use ($fund, $typeCode, $amount, $meta) {
            $locked = RevolvingFund::whereKey($fund->id)->lockForUpdate()->firstOrFail();
            $type = RevolvingFundTransactionType::where('code', $typeCode)->firstOrFail();

            $amount = round((float) $amount, 2);
            $balance = round((float) $locked->current_balance + $type->sign * $amount, 2);

            $locked->update(['current_balance' => $balance]);
            $fund->current_balance = $balance; // keep the caller's instance in step

            return RevolvingFundTransaction::create([
                'revolving_fund_id' => $fund->id,
                'fund_request_id' => $meta['fund_request_id'] ?? null,
                'expense_id' => $meta['expense_id'] ?? null,
                'transaction_type_id' => $type->id,
                'amount' => $amount,
                'balance_after' => $balance,
                'transaction_date' => $meta['transaction_date'] ?? now()->toDateString(),
                'reference_no' => $meta['reference_no'] ?? null,
                'description' => $meta['description'] ?? null,
                'posted_by' => $meta['posted_by'] ?? null,
            ]);
        });
    }
}
