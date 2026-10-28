<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApVoucher extends Model
{
    protected $fillable = ['fund_request_id', 'apv_no', 'apv_date', 'amount', 'created_by'];
    protected $casts = ['apv_date' => 'date', 'amount' => 'decimal:2'];

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }

    public function checkVoucher()
    {
        return $this->hasOne(CheckVoucher::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
