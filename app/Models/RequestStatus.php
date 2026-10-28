<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    // Codes mirror Phase 1 §5 — keep in sync with ProcessASeeder.
    public const DRAFT = 'DRAFT';
    public const RETURNED_TO_REQUESTOR = 'RETURNED_TO_REQUESTOR';
    public const FOR_OE_VALIDATION = 'FOR_OE_VALIDATION';
    public const RETURNED_TO_OE = 'RETURNED_TO_OE';
    public const FOR_ACCT_VALIDATION = 'FOR_ACCT_VALIDATION';
    public const RETURNED_TO_STAFF = 'RETURNED_TO_STAFF';
    public const FOR_HEAD_APPROVAL = 'FOR_HEAD_APPROVAL';
    public const FOR_AP_VOUCHER = 'FOR_AP_VOUCHER';
    public const FOR_CV_NUMBER = 'FOR_CV_NUMBER';
    public const FOR_DISBURSEMENT = 'FOR_DISBURSEMENT';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';
}
