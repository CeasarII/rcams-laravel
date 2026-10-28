<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history for one Questionable Expense. */
class QuestionableExpenseEvent extends Model
{
    protected $fillable = [
        'questionable_expense_id', 'actor_id', 'role_id', 'action',
        'from_status_id', 'to_status_id', 'comments',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function fromStatus()
    {
        return $this->belongsTo(QuestionableExpenseStatus::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(QuestionableExpenseStatus::class, 'to_status_id');
    }
}
