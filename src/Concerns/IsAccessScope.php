<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Support\Models;

/**
 * Marks a model used as a scope: deleting it removes the grants, role assignments and
 * role definitions scoped to it, so a later scope that reuses the key never inherits
 * them. Soft deletes keep them until forceDelete.
 */
trait IsAccessScope
{
    public static function bootIsAccessScope(): void
    {
        $cleanup = static function (Model $scope): void {
            DB::transaction(function () use ($scope): void {
                foreach ([Models::permission(), Models::assignedRole()] as $model) {
                    $model::query()
                        ->where('scope_type', $scope->getMorphClass())
                        ->where('scope_id', $scope->getKey())
                        ->delete();
                }

                // One at a time, so each role's own deleted event removes its permission rows.
                Models::role()::query()
                    ->where('scope_type', $scope->getMorphClass())
                    ->where('scope_id', $scope->getKey())
                    ->get()
                    ->each(fn (Model $role): ?bool => $role->delete());
            });

            Valkyrie::refresh();
        };

        in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::registerModelEvent('forceDeleted', $cleanup)
            : static::deleted($cleanup);
    }
}
