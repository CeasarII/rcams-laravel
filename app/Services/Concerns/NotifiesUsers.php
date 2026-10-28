<?php

namespace App\Services\Concerns;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Shared by the liquidation and questionable-expense workflow services. */
trait NotifiesUsers
{
    protected static function fail(string $key, string $message): ValidationException
    {
        return ValidationException::withMessages([$key => $message]);
    }

    protected function notifyUser(int $userId, Model $reference, string $title, string $message): void
    {
        AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
            'is_read' => false,
        ]);
    }

    protected function notifyRole(string $roleCode, Model $reference, string $title, string $message): void
    {
        foreach (User::whereHas('roles', fn ($q) => $q->where('code', $roleCode))->pluck('id') as $userId) {
            $this->notifyUser($userId, $reference, $title, $message);
        }
    }

    /** Only the OE(s) scoped to the cash advance's vessel (Sea Base) or department (Shore Base). */
    protected function notifyOe(Model $scope, Model $reference, string $title, string $message): void
    {
        $query = User::whereHas('roles', fn ($q) => $q->where('code', 'OE'));

        $scope->vessel_id !== null
            ? $query->whereHas('vessels', fn ($q) => $q->where('vessels.id', $scope->vessel_id))
            : $query->whereHas('departments', fn ($q) => $q->where('departments.id', $scope->department_id));

        foreach ($query->pluck('id') as $userId) {
            $this->notifyUser($userId, $reference, $title, $message);
        }
    }

    /** Same scoping rule as FundRequestWorkflowService::assertOeScope(), applied to the cash advance's request. */
    protected function assertOeScope(Model $scope, User $actor): void
    {
        $scoped = $scope->vessel_id !== null
            ? $actor->vessels()->where('vessels.id', $scope->vessel_id)->exists()
            : $actor->departments()->where('departments.id', $scope->department_id)->exists();

        if (! $scoped) {
            throw self::fail('scope', $scope->vessel_id !== null
                ? 'You are not assigned to validate requests for this vessel.'
                : 'You are not assigned to validate requests for this department.');
        }
    }
}
