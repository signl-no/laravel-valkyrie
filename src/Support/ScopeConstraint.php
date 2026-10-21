<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Contracts\ScopedEntity;

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

    /**
     * The scope a check runs in. A ScopedEntity decides outright, so a null access scope
     * means global and never falls back to the caller's scope.
     *
     * @param  Model|class-string<Model>|string|null  $entity
     */
    public static function effective(Model|string|null $entity, ?Model $scope): ?Model
    {
        return $entity instanceof ScopedEntity ? $entity->accessScope() : $scope;
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
