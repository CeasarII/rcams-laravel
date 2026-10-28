<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundExpenseItem extends Model
{
    protected $fillable = [
        'expense_id', 'item_date', 'purpose', 'description', 'reference', 'supplier_name', 'tin',
        'nature_of_cost_id', 'vat_nature', 'vat_rate', 'gross_amount', 'net_amount', 'input_tax',
        'liquidation_item_status_id',
    ];

    protected $casts = [
        'item_date' => 'date',
        'vat_rate' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'input_tax' => 'decimal:2',
    ];

    public function expense()
    {
        return $this->belongsTo(RevolvingFundExpense::class, 'expense_id');
    }

    public function natureOfCost()
    {
        return $this->belongsTo(NatureOfCost::class);
    }

    public function status()
    {
        return $this->belongsTo(LiquidationItemStatus::class, 'liquidation_item_status_id');
    }

    /** Soft copies of the receipts — shared polymorphic attachments table. */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function questionableExpense()
    {
        return $this->hasOne(QuestionableExpense::class, 'revolving_fund_expense_item_id');
    }
}
