<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NatureOfCost extends Model
{
    protected $fillable = ['code', 'label', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(LiquidationItem::class, 'nature_of_cost_id');
    }
}
