<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselType extends Model
{
    protected $fillable = ['code', 'label', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function vessels()
    {
        return $this->hasMany(Vessel::class);
    }
}
