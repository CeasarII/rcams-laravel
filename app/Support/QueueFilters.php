<?php

namespace App\Support;

use Closure;

/**
 * The "View" dropdown shared by the list pages: queues waiting on the user's
 * role(s), a combined "Needs my action" (default), then general views.
 * Each filter is ['label' => string, 'apply' => fn (Builder $q) => void].
 */
final class QueueFilters
{
    public static function build(array $queues, array $general): array
    {
        $filters = [];

        if ($queues) {
            $filters['action'] = ['label' => 'Needs my action', 'apply' => function ($q) use ($queues) {
                $q->where(function ($w) use ($queues) {
                    foreach ($queues as $queue) {
                        $w->orWhere(fn ($x) => $queue['apply']($x));
                    }
                });
            }];

            // One queue is already what "Needs my action" shows.
            if (count($queues) > 1) {
                $filters += $queues;
            }
        }

        return $filters + $general;
    }

    public static function resolve(array $filters, mixed $requested): string
    {
        return is_string($requested) && isset($filters[$requested]) ? $requested : array_key_first($filters);
    }

    /** @param  Closure  $freshQuery  returns a new, already access-scoped query each call */
    public static function options(array $filters, Closure $freshQuery): array
    {
        $options = [];

        foreach ($filters as $key => $filter) {
            $query = $freshQuery();
            $filter['apply']($query);
            $options[$key] = ['label' => $filter['label'], 'count' => $query->count()];
        }

        return $options;
    }
}
