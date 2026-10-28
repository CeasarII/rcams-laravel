<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundExpense extends Model
{
    protected $fillable = [
        'control_no', 'voyage_id', 'prepared_by', 'expense_date', 'total_amount',
        'status_id', 'remarks', 'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function voyage()
    {
        return $this->belongsTo(RevolvingFundVoyage::class, 'voyage_id');
    }

    /** The fund this replenishes (via its voyage). */
    public function fund(): RevolvingFund
    {
        return $this->voyage->revolvingFund;
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function status()
    {
        return $this->belongsTo(RevolvingFundExpenseStatus::class, 'status_id');
    }

    public function items()
    {
        return $this->hasMany(RevolvingFundExpenseItem::class, 'expense_id')->orderBy('item_date')->orderBy('id');
    }

    public function approvals()
    {
        return $this->hasMany(RevolvingFundExpenseApproval::class, 'expense_id')->orderBy('approval_date')->orderBy('id');
    }

    public function voucher()
    {
        return $this->hasOne(RevolvingFundVoucher::class, 'expense_id');
    }

    /**
     * The replenishment amount is every item, VAT-inclusive — questionable
     * items ride along (replenished, but they stay Advances to Employee until
     * their Questionable Expense is resolved).
     */
    public function recalculateTotal(): void
    {
        $this->total_amount = round((float) $this->items()->sum('gross_amount'), 2);
        $this->save();
    }
}
