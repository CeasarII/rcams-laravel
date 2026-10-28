<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevolvingFundExpenseStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    // Process B in the process guide (keep in sync with RevolvingFundSeeder):
    public const DRAFT = 'DRAFT';
    public const FOR_OE_VALIDATION = 'FOR_OE_VALIDATION';           // 2 Approver/OE verifies + validates (soft copy)
    public const RETURNED_TO_REQUESTOR = 'RETURNED_TO_REQUESTOR';   //   not valid: Requestor revises details and tag
    public const FOR_STAFF_VALIDATION = 'FOR_STAFF_VALIDATION';     // 3 Accounting Staff validates
    public const RETURNED_TO_OE = 'RETURNED_TO_OE';                 //   not valid: OE revises details and request
    public const FOR_HEAD_APPROVAL = 'FOR_HEAD_APPROVAL';           // 4 Head reviews and decides
    public const RETURNED_TO_STAFF = 'RETURNED_TO_STAFF';           //   not approved: back to Staff (step 3)
    public const FOR_AP_VOUCHER = 'FOR_AP_VOUCHER';                 // 5 Staff creates the AP voucher
    public const FOR_HC_TAG = 'FOR_HC_TAG';                         // 6 Head sets the tag status: HC pending (only if items are tagged)
    public const FOR_CV_NUMBER = 'FOR_CV_NUMBER';                   // 7 Treasury creates the CV number
    public const FOR_DISBURSEMENT = 'FOR_DISBURSEMENT';             // 8 Treasury disburses
    public const COMPLETED = 'COMPLETED';                           // 9 Requestor notified

    public const REQUESTOR_EDITABLE = [self::DRAFT, self::RETURNED_TO_REQUESTOR];
}
