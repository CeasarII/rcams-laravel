<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionableExpense extends Model
{
    protected $fillable = [
        'control_no', 'liquidation_item_id', 'revolving_fund_expense_item_id', 'status_id', 'tagged_by', 'tagged_role', 'tagged_at', 'reason',
        'hard_copy_received_on', 'hard_copy_valid', 'hard_copy_checked_by', 'hard_copy_checked_at', 'hard_copy_remarks',
        'ir_no', 'ir_date', 'ir_generated_by', 'ir_received_by', 'ir_received_at',
        'deduction_amount', 'deduction_remarks', 'deduction_by', 'deduction_at',
        'proof_secured_by', 'proof_secured_at', 'resolution', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'tagged_at' => 'datetime',
        'hard_copy_received_on' => 'date',
        'hard_copy_valid' => 'boolean',
        'hard_copy_checked_at' => 'datetime',
        'ir_date' => 'date',
        'ir_received_at' => 'datetime',
        'deduction_amount' => 'decimal:2',
        'deduction_at' => 'datetime',
        'proof_secured_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public const RESOLUTION_EXPENSE = 'EXPENSE';
    public const RESOLUTION_DEDUCTED = 'DEDUCTED';

    public function item()
    {
        return $this->belongsTo(LiquidationItem::class, 'liquidation_item_id');
    }

    public function revolvingFundExpenseItem()
    {
        return $this->belongsTo(RevolvingFundExpenseItem::class, 'revolving_fund_expense_item_id');
    }

    public function status()
    {
        return $this->belongsTo(QuestionableExpenseStatus::class, 'status_id');
    }

    // ---- one QE, two possible sources ---------------------------------
    // Flagged when liquidating a cash advance (liquidation item) or when
    // replenishing a revolving fund (expense item). Everything downstream asks
    // these helpers instead of reaching into either source directly.

    public function isRevolvingFund(): bool
    {
        return $this->revolving_fund_expense_item_id !== null;
    }

    /** The flagged line: a LiquidationItem or a RevolvingFundExpenseItem (same amount/VAT/status fields). */
    public function sourceItem(): LiquidationItem|RevolvingFundExpenseItem
    {
        return $this->isRevolvingFund() ? $this->revolvingFundExpenseItem : $this->item;
    }

    /** The employee whose expense it is. */
    public function owner(): User
    {
        return $this->isRevolvingFund()
            ? $this->revolvingFundExpenseItem->expense->preparer
            : $this->item->liquidation->preparer;
    }

    public function ownerId(): int
    {
        return $this->isRevolvingFund()
            ? $this->revolvingFundExpenseItem->expense->prepared_by
            : $this->item->liquidation->prepared_by;
    }

    /** LQ-… or RFE-… */
    public function sourceControlNo(): string
    {
        return $this->isRevolvingFund()
            ? $this->revolvingFundExpenseItem->expense->control_no
            : $this->item->liquidation->control_no;
    }

    /** Whatever carries vessel_id / department_id for OE scoping: the cash advance request, or the fund. */
    public function scopeModel(): \Illuminate\Database\Eloquent\Model
    {
        return $this->isRevolvingFund()
            ? $this->revolvingFundExpenseItem->expense->voyage->revolvingFund
            : $this->item->liquidation->fundRequest;
    }

    public function taggedBy()
    {
        return $this->belongsTo(User::class, 'tagged_by');
    }

    public function events()
    {
        return $this->hasMany(QuestionableExpenseEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /** Proof of deduction / authority to deduct — shared polymorphic attachments table. */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isClosed(): bool
    {
        return $this->status->code === QuestionableExpenseStatus::CLOSED;
    }

    public function logEvent(User $actor, string $roleCode, string $action, ?int $fromStatusId, int $toStatusId, ?string $comments = null): void
    {
        $this->events()->create([
            'actor_id' => $actor->id,
            'role_id' => Role::where('code', $roleCode)->firstOrFail()->id,
            'action' => $action,
            'from_status_id' => $fromStatusId,
            'to_status_id' => $toStatusId,
            'comments' => $comments,
        ]);
    }
}
