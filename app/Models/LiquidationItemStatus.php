<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationItemStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const EXPENSE = 'EXPENSE';                           // normal, counts as liquidated
    public const QUESTIONABLE = 'QUESTIONABLE';                 // tagged: stays "Advances to Employee"
    public const SETTLED_BY_DEDUCTION = 'SETTLED_BY_DEDUCTION'; // QE closed via salary deduction
}
