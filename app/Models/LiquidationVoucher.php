<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationVoucher extends Model
{
    protected $fillable = [
        'liquidation_id', 'apv_no', 'apv_date', 'amount', 'prepared_by',
        'cv_no', 'cv_date', 'payment_mode', 'payment_reference', 'reimbursed_at', 'processed_by',
    ];

    protected $casts = [
        'apv_date' => 'date',
        'cv_date' => 'date',
        'amount' => 'decimal:2',
        'reimbursed_at' => 'datetime',
    ];

    public function liquidation()
    {
        return $this->belongsTo(CashAdvanceLiquidation::class, 'liquidation_id');
    }
}
