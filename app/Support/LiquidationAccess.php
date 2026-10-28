<?php

namespace App\Support;

use App\Models\CashAdvanceLiquidation;
use App\Models\QuestionableExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see which liquidation / questionable expense. Mirrors the fund
 * request rule (a Requestor sees only their own; OE, Accounting, Treasury and
 * Admin see all) and adds Crewing, who only need the questionable expenses
 * that reach their salary-deduction step.
 *
 * Used by the policies (single record) and the list pages (query scope) so
 * the two can never disagree.
 */
final class LiquidationAccess
{
    private const SEES_ALL = ['OE', 'ACCT_STAFF', 'ACCT_HEAD', 'TREASURY', 'ADMIN'];

    public static function roles(User $user): array
    {
        return $user->roles()->pluck('code')->all();
    }

    public static function seesAll(array $roles): bool
    {
        return (bool) array_intersect($roles, self::SEES_ALL);
    }

    public static function scopeLiquidations(Builder $query, User $user, ?array $roles = null): Builder
    {
        $roles ??= self::roles($user);

        if (self::seesAll($roles)) {
            return $query;
        }

        return $query->where(function ($w) use ($user, $roles) {
            $any = false;

            if (in_array('REQUESTOR', $roles, true)) {
                $w->orWhere('prepared_by', $user->id);
                $any = true;
            }
            if (in_array('CREWING', $roles, true)) {
                $w->orWhereHas('questionableExpenses');
                $any = true;
            }
            if (! $any) {
                $w->whereRaw('1 = 0');
            }
        });
    }

    public static function canViewLiquidation(User $user, CashAdvanceLiquidation $liquidation): bool
    {
        $roles = self::roles($user);

        if (self::seesAll($roles)) {
            return true;
        }
        if (in_array('REQUESTOR', $roles, true) && $liquidation->prepared_by === $user->id) {
            return true;
        }

        return in_array('CREWING', $roles, true) && $liquidation->questionableExpenses()->exists();
    }

    public static function scopeQuestionableExpenses(Builder $query, User $user, ?array $roles = null): Builder
    {
        $roles ??= self::roles($user);

        if (self::seesAll($roles) || in_array('CREWING', $roles, true)) {
            return $query;
        }

        if (in_array('REQUESTOR', $roles, true)) {
            return $query->where(function ($w) use ($user) {
                $w->whereHas('item.liquidation', fn ($q) => $q->where('prepared_by', $user->id))
                    ->orWhereHas('revolvingFundExpenseItem.expense', fn ($q) => $q->where('prepared_by', $user->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewQuestionableExpense(User $user, QuestionableExpense $qe): bool
    {
        $roles = self::roles($user);

        if (self::seesAll($roles) || in_array('CREWING', $roles, true)) {
            return true;
        }

        return in_array('REQUESTOR', $roles, true) && $qe->ownerId() === $user->id;
    }
}
