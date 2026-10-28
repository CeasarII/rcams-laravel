<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvanceLiquidation extends Model
{
    protected $table = 'cash_advance_liquidations';

    protected $fillable = [
        'control_no', 'fund_request_id', 'prepared_by', 'date_prepared', 'purpose',
        'cash_advance_amount', 'total_amount', 'liquidated_amount',
        'amount_to_return', 'amount_to_reimburse',
        'liquidation_status_id', 'submitted_at', 'closed_at', 'cash_return_reference',
    ];

    protected $casts = [
        'date_prepared' => 'date',
        'cash_advance_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'liquidated_amount' => 'decimal:2',
        'amount_to_return' => 'decimal:2',
        'amount_to_reimburse' => 'decimal:2',
        'submitted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function status()
    {
        return $this->belongsTo(LiquidationStatus::class, 'liquidation_status_id');
    }

    public function items()
    {
        return $this->hasMany(LiquidationItem::class, 'liquidation_id')->orderBy('item_date')->orderBy('id');
    }

    public function approvals()
    {
        return $this->hasMany(LiquidationApproval::class, 'liquidation_id')->orderBy('approval_date')->orderBy('id');
    }

    public function voucher()
    {
        return $this->hasOne(LiquidationVoucher::class, 'liquidation_id');
    }

    public function questionableExpenses()
    {
        return $this->hasManyThrough(QuestionableExpense::class, LiquidationItem::class, 'liquidation_id', 'liquidation_item_id');
    }

    /**
     * Amounts are always derived from the items, never taken from input:
     *
     *   total             = every item (VAT-inclusive gross)
     *   liquidated        = items tagged Expense
     *   held as advances  = items tagged Questionable, or settled by salary deduction
     *   to return         = unspent cash = advance - liquidated - held
     *   to reimburse      = valid expenses above the advance = liquidated - advance
     *
     * Questionable items are NOT liquidated: they stay "Advances to Employee"
     * until their Questionable Expense is closed. Totals follow the items only
     * until Accounting verifies; after that they are frozen so the APV and the
     * reimbursement can't drift (see LiquidationStatus::TOTALS_OPEN).
     */
    public function recalculateTotals(): void
    {
        if (! in_array($this->status->code, LiquidationStatus::TOTALS_OPEN, true)) {
            return;
        }

        $items = $this->items()->with('status')->get();
        $sum = fn (array $codes) => round((float) $items
            ->filter(fn ($i) => in_array($i->status->code, $codes, true))
            ->sum('gross_amount'), 2);

        $expense = $sum([LiquidationItemStatus::EXPENSE]);
        $held = $sum([LiquidationItemStatus::QUESTIONABLE, LiquidationItemStatus::SETTLED_BY_DEDUCTION]);
        $advance = (float) $this->cash_advance_amount;

        $this->total_amount = round((float) $items->sum('gross_amount'), 2);
        $this->liquidated_amount = $expense;
        $this->amount_to_return = round(max(0, $advance - $expense - $held), 2);
        $this->amount_to_reimburse = round(max(0, $expense - $advance), 2);
        $this->save();
    }

    /** Amount currently sitting as Advances to Employee because of questionable items. */
    public function heldAsAdvances(): float
    {
        return round((float) $this->items()
            ->whereHas('status', fn ($q) => $q->whereIn('code', [
                LiquidationItemStatus::QUESTIONABLE, LiquidationItemStatus::SETTLED_BY_DEDUCTION,
            ]))->sum('gross_amount'), 2);
    }
}
