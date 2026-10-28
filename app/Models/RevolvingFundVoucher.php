<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundVoucher extends Model
{
    protected $fillable = [
        'expense_id', 'apv_no', 'apv_date', 'amount', 'prepared_by',
        'cv_no', 'cv_date', 'payment_mode', 'payment_reference', 'disbursed_at', 'processed_by',
    ];

    protected $casts = ['apv_date' => 'date', 'cv_date' => 'date', 'amount' => 'decimal:2', 'disbursed_at' => 'datetime'];

    public function expense()
    {
        return $this->belongsTo(RevolvingFundExpense::class, 'expense_id');
    }
}
