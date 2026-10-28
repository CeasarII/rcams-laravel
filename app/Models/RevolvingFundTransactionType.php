<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundTransactionType extends Model
{
    protected $fillable = ['code', 'label', 'sign', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const FUNDING = 'FUNDING';
    public const EXPENSE = 'EXPENSE';
    public const REPLENISHMENT = 'REPLENISHMENT';
}
