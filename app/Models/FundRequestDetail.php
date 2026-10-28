<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRequestDetail extends Model
{
    protected $fillable = ['fund_request_id', 'purpose', 'description', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }
}
