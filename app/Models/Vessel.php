<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    protected $fillable = ['vessel_type_id', 'company_id', 'name', 'imo_no', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function vesselType()
    {
        return $this->belongsTo(VesselType::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
