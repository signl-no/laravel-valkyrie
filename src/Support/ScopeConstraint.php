<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Restricts rows to a global scope (null) or an exact scope match; never inherited.
 */
final class ScopeConstraint
{
    public static function apply(Builder $query, string $table, ?Model $scope): Builder
    {
        return $query->where(static function (Builder $query) use ($table, $scope): void {
            $query->whereNull("{$table}.scope_type");

            if ($scope !== null) {
                $query->orWhere(static function (Builder $query) use ($table, $scope): void {
                    $query
                        ->where("{$table}.scope_type", $scope->getMorphClass())
                        ->where("{$table}.scope_id", $scope->getKey());
                });
            }
        });
    }

    public static function matches(?string $scopeType, int|string|null $scopeId, ?Model $scope): bool
    {
        if ($scopeType === null) {
            return $scope === null;
        }

        return $scope !== null
            && $scopeType === $scope->getMorphClass()
            && (string) $scopeId === (string) $scope->getKey();
    }
}
