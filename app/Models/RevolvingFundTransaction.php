<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundTransaction extends Model
{
    protected $fillable = [
        'revolving_fund_id', 'fund_request_id', 'expense_id', 'transaction_type_id',
        'amount', 'balance_after', 'transaction_date', 'reference_no', 'description', 'posted_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'balance_after' => 'decimal:2', 'transaction_date' => 'date'];

    public function revolvingFund()
    {
        return $this->belongsTo(RevolvingFund::class);
    }

    public function type()
    {
        return $this->belongsTo(RevolvingFundTransactionType::class, 'transaction_type_id');
    }

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }

    public function expense()
    {
        return $this->belongsTo(RevolvingFundExpense::class, 'expense_id');
    }
}
