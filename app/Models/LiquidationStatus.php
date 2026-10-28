<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    // Keep in sync with LiquidationSeeder.
    public const DRAFT = 'DRAFT';
    public const FOR_OE_VALIDATION = 'FOR_OE_VALIDATION';
    public const RETURNED_FOR_EDIT = 'RETURNED_FOR_EDIT';           // OE: "Liquidation okay?" = No  -> Edit liquidation
    public const FOR_STAFF_VERIFICATION = 'FOR_STAFF_VERIFICATION';
    public const RETURNED_FOR_REVISION = 'RETURNED_FOR_REVISION';   // Staff: "Verification okay?" = No -> Revise liquidation
    public const FOR_APV_PREPARATION = 'FOR_APV_PREPARATION';
    public const FOR_APV_REVIEW = 'FOR_APV_REVIEW';
    public const FOR_HEAD_CLOSURE = 'FOR_HEAD_CLOSURE';             // nothing payable to requestor
    public const FOR_REIMBURSEMENT = 'FOR_REIMBURSEMENT';
    public const CLOSED = 'CLOSED';

    /** Requestor can change items/header in these. */
    public const EDITABLE = [self::DRAFT, self::RETURNED_FOR_EDIT, self::RETURNED_FOR_REVISION];

    /** Totals follow the items only up to verification; after that they are frozen for the APV. */
    public const TOTALS_OPEN = [
        self::DRAFT, self::RETURNED_FOR_EDIT, self::RETURNED_FOR_REVISION,
        self::FOR_OE_VALIDATION, self::FOR_STAFF_VERIFICATION,
    ];
}
