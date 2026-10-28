<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const SUBMITTED = 'SUBMITTED';
    public const VALIDATED = 'VALIDATED';
    public const RETURNED = 'RETURNED';
    public const APPROVED = 'APPROVED';
    public const NOT_APPROVED = 'NOT_APPROVED';
    public const APV_CREATED = 'APV_CREATED';
    public const CV_CREATED = 'CV_CREATED';
    public const DISBURSED = 'DISBURSED';
    public const CANCELLED = 'CANCELLED';
    public const REIMBURSED = 'REIMBURSED';
    public const CLOSED = 'CLOSED';
    public const QE_TAGGED = 'QE_TAGGED';
    public const QE_UNTAGGED = 'QE_UNTAGGED';
    public const HC_PENDING_SET = 'HC_PENDING_SET';
}
