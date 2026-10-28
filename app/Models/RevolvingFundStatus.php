<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const ACTIVE = 'ACTIVE';
    public const CLOSED = 'CLOSED';
}
