<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRequestType extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const CASH_ADVANCE = 'CASH_ADVANCE';
    public const REVOLVING_FUND = 'REVOLVING_FUND';
}
