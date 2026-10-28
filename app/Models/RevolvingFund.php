<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFund extends Model
{
    protected $fillable = [
        'fund_code', 'fund_name', 'vessel_id', 'company_id', 'department_id', 'custodian_id',
        'fund_limit', 'current_balance', 'status_id',
    ];

    protected $casts = ['fund_limit' => 'decimal:2', 'current_balance' => 'decimal:2'];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function status()
    {
        return $this->belongsTo(RevolvingFundStatus::class, 'status_id');
    }

    public function voyages()
    {
        return $this->hasMany(RevolvingFundVoyage::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function transactions()
    {
        return $this->hasMany(RevolvingFundTransaction::class)->orderByDesc('id');
    }

    public function expenses()
    {
        return $this->hasManyThrough(RevolvingFundExpense::class, RevolvingFundVoyage::class, 'revolving_fund_id', 'voyage_id');
    }

    public function openVoyage(): ?RevolvingFundVoyage
    {
        return $this->voyages()->whereHas('status', fn ($q) => $q->where('code', RevolvingFundVoyageStatus::OPEN))->first();
    }

    public function isActive(): bool
    {
        return $this->status->code === RevolvingFundStatus::ACTIVE;
    }

    /** Room left under the limit for new funding. */
    public function headroom(): float
    {
        return round(max(0, (float) $this->fund_limit - (float) $this->current_balance), 2);
    }
}
