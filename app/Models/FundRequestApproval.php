<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per workflow transition on a FundRequest. Append-only — the app
 * layer never updates or deletes rows here (see FundRequestWorkflowService).
 */
class FundRequestApproval extends Model
{
    protected $fillable = [
        'fund_request_id', 'approver_id', 'role_id', 'approval_level',
        'approval_status_id', 'from_request_status_id', 'to_request_status_id',
        'approval_date', 'comments',
    ];

    protected $casts = ['approval_date' => 'datetime'];

    public function fundRequest()
    {
        return $this->belongsTo(FundRequest::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function approvalStatus()
    {
        return $this->belongsTo(ApprovalStatus::class);
    }

    public function fromStatus()
    {
        return $this->belongsTo(RequestStatus::class, 'from_request_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(RequestStatus::class, 'to_request_status_id');
    }
}
