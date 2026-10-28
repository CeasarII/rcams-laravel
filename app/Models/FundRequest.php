<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRequest extends Model
{
    protected $fillable = [
        'control_no', 'requestor_id', 'department_id', 'company_id',
        'category', 'vessel_id', 'revolving_fund_id', 'fund_request_type_id',
        'amount_requested', 'request_date', 'purpose',
        'request_status_id', 'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'request_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const CATEGORY_SHORE_BASE = 'Shore Base';
    public const CATEGORY_SEA_BASE = 'Sea Base';

    public function revolvingFund()
    {
        return $this->belongsTo(RevolvingFund::class);
    }

    public function requestor()
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function fundRequestType()
    {
        return $this->belongsTo(FundRequestType::class);
    }

    public function requestStatus()
    {
        return $this->belongsTo(RequestStatus::class);
    }

    public function details()
    {
        return $this->hasMany(FundRequestDetail::class);
    }

    public function approvals()
    {
        // Workflow history, oldest first.
        return $this->hasMany(FundRequestApproval::class)->orderBy('approval_date');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function apVoucher()
    {
        return $this->hasOne(ApVoucher::class);
    }

    /** Recomputes amount_requested from line items. Never trust client totals. */
    public function recalculateAmount(): void
    {
        $this->amount_requested = $this->details()->sum('amount');
        $this->save();
    }
}
