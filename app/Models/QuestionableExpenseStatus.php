<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionableExpenseStatus extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public const TAGGED = 'TAGGED';                                 // 0  revolving fund: flagged; Head has not yet set HC pending (replenishment step 6)
    public const WAITING_HARD_COPY = 'WAITING_HARD_COPY';           // 1  Staff validates the file, now with hard copy
    public const FOR_IR = 'FOR_IR';                                 // 2-3 hard copy not valid: keep tag, generate IR
    public const FOR_OE_RECEIPT = 'FOR_OE_RECEIPT';                 // 4  Approver/OE receives the IR
    public const FOR_SALARY_DEDUCTION = 'FOR_SALARY_DEDUCTION';     // 5  Crewing
    public const FOR_PROOF_OF_DEDUCTION = 'FOR_PROOF_OF_DEDUCTION'; // 6  Crewing
    public const FOR_CLOSURE = 'FOR_CLOSURE';                       // 7  Staff closes
    public const CLOSED = 'CLOSED';
}
