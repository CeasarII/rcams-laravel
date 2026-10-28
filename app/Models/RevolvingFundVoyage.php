<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundVoyage extends Model
{
    protected $fillable = [
        'revolving_fund_id', 'voyage_no', 'start_date', 'end_date',
        'beginning_balance', 'ending_balance', 'status_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'beginning_balance' => 'decimal:2',
        'ending_balance' => 'decimal:2',
    ];

    public function revolvingFund()
    {
        return $this->belongsTo(RevolvingFund::class);
    }

    public function status()
    {
        return $this->belongsTo(RevolvingFundVoyageStatus::class, 'status_id');
    }

    public function expenses()
    {
        return $this->hasMany(RevolvingFundExpense::class, 'voyage_id');
    }

    public function isOpen(): bool
    {
        return $this->status->code === RevolvingFundVoyageStatus::OPEN;
    }
}
