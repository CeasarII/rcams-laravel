<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckVoucher extends Model
{
    protected $fillable = [
        'ap_voucher_id', 'cv_no', 'cv_date', 'amount_disbursed',
        'disbursed_at', 'payment_mode', 'payment_reference', 'processed_by',
    ];

    protected $casts = [
        'cv_date' => 'date',
        'amount_disbursed' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    public function apVoucher()
    {
        return $this->belongsTo(ApVoucher::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
