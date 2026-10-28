<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationItem extends Model
{
    protected $fillable = [
        'liquidation_id', 'item_date', 'purpose', 'reference', 'supplier_name', 'tin',
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

    public function liquidation()
    {
        return $this->belongsTo(CashAdvanceLiquidation::class, 'liquidation_id');
    }

    public function natureOfCost()
    {
        return $this->belongsTo(NatureOfCost::class);
    }

    public function status()
    {
        return $this->belongsTo(LiquidationItemStatus::class, 'liquidation_item_status_id');
    }

    public function attachments()
    {
        return $this->hasMany(LiquidationItemAttachment::class, 'liquidation_item_id');
    }

    public function questionableExpense()
    {
        return $this->hasOne(QuestionableExpense::class, 'liquidation_item_id');
    }
}
