<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only workflow history for a revolving fund replenishment. */
class RevolvingFundExpenseApproval extends Model
{
    protected $fillable = [
        'expense_id', 'approver_id', 'role_id', 'approval_status_id',
        'from_status_id', 'to_status_id', 'approval_date', 'comments',
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
        return $this->belongsTo(RevolvingFundExpenseStatus::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(RevolvingFundExpenseStatus::class, 'to_status_id');
    }
}
