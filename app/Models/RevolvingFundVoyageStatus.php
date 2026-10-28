<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundVoyageStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const OPEN = 'OPEN';
    public const CLOSED = 'CLOSED';
}
