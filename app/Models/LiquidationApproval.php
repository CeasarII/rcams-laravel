<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only workflow history for a liquidation (see LiquidationWorkflowService). */
class LiquidationApproval extends Model
{
    protected $fillable = [
        'liquidation_id', 'approver_id', 'role_id', 'approval_level', 'approval_status_id',
        'from_liquidation_status_id', 'to_liquidation_status_id', 'approval_date', 'comments',
    ];

    protected $casts = ['approval_date' => 'datetime'];

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
        return $this->belongsTo(LiquidationStatus::class, 'from_liquidation_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(LiquidationStatus::class, 'to_liquidation_status_id');
    }
}
