<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Concerns;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Grants\GrantBuilder;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\Support\Models;
use Signl\Valkyrie\Support\Morph;
use Signl\Valkyrie\Support\ScopeConstraint;

/**
 * Makes a model an actor: it can hold roles and abilities.
 */
trait HasRolesAndAbilities
{
    /**
     * A deleted actor takes its grants and role assignments with it, so a later actor
     * that reuses the key never inherits them. Soft deletes keep them until forceDelete.
     */
    public static function bootHasRolesAndAbilities(): void
    {
        $cleanup = static function (Model $actor): void {
            foreach ([Models::permission(), Models::assignedRole()] as $model) {
                $model::query()
                    ->where('entity_type', $actor->getMorphClass())
                    ->where('entity_id', $actor->getKey())
                    ->delete();
            }

            Valkyrie::refreshFor($actor);
        };

        in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::registerModelEvent('forceDeleted', $cleanup)
            : static::deleted($cleanup);
    }

    /**
     * Directly granted abilities; the pivot carries forbidden and scope.
     *
     * @return MorphToMany<Ability, $this>
     */
    public function abilities(): MorphToMany
    {
        return $this
            ->morphToMany(Models::ability(), 'entity', config()->string('valkyrie.tables.permissions'), 'entity_id', 'ability_id')
            ->withPivot('forbidden', 'scope_type', 'scope_id');
    }

    public function allow(?Model $on = null): GrantBuilder
    {
        return Valkyrie::allow($this, $on);
    }

    public function assign(string|Role $role, ?Model $on = null): static
    {
        Valkyrie::assign($role, $this, $on);

        return $this;
    }

    public function forbid(?Model $on = null): GrantBuilder
    {
        return Valkyrie::forbid($this, $on);
    }

    /**
     * @return Collection<int, Ability>
     */
    public function getAbilities(): Collection
    {
        return $this->abilities()->wherePivot('forbidden', false)->get();
    }

    /**
     * @return Collection<int, Ability>
     */
    public function getForbiddenAbilities(): Collection
    {
        return $this->abilities()->wherePivot('forbidden', true)->get();
    }

    /**
     * Without a scope: holds the role anywhere. With one: globally or in that scope.
     */
    public function isA(string $role, ?Model $scope = null): bool
    {
        return Valkyrie::holdsRole($this, $role, $scope);
    }

    public function isAll(string ...$roles): bool
    {
        return collect($roles)->every(fn (string $role): bool => $this->isA($role));
    }

    public function isAn(string $role, ?Model $scope = null): bool
    {
        return $this->isA($role, $scope);
    }

    public function isNotA(string $role, ?Model $scope = null): bool
    {
        return ! $this->isA($role, $scope);
    }

    public function retract(string|Role $role, ?Model $on = null): static
    {
        Valkyrie::retract($role, $this, $on);

        return $this;
    }

    /**
     * Every role assignment, one row per scope; the pivot carries the scope columns.
     *
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this
            ->morphToMany(Models::role(), 'entity', config()->string('valkyrie.tables.assigned_roles'), 'entity_id', 'role_id')
            ->withPivot('scope_type', 'scope_id');
    }

    /**
     * Actors that can perform the ability and are not forbidden it. A query-level
     * approximation of the resolver: ownership-only grants are excluded.
     * A ScopedEntity's access scope replaces `$scope`, as in the resolver.
     *
     * @param  Builder<static>  $query
     * @param  Model|class-string<Model>|null  $entity
     * @return Builder<static>
     */
    #[Scope]
    protected function whereCan(Builder $query, string $ability, Model|string|null $entity = null, ?Model $scope = null): Builder
    {
        $abilities = config()->string('valkyrie.tables.abilities');
        $permissions = config()->string('valkyrie.tables.permissions');
        $assignedRoles = config()->string('valkyrie.tables.assigned_roles');
        $entityType = Morph::type($entity);
        $entityKey = Morph::key($entity);
        $scope = ScopeConstraint::effective($entity, $scope);

        $abilityMatches = function (Builder $query, bool $forbidden) use ($abilities, $permissions, $ability, $entityType, $entityKey, $scope): void {
            $query
                ->whereIn("{$abilities}.name", [$ability, Morph::Everything])
                ->where(function (Builder $query) use ($abilities, $entityType): void {
                    $query->where("{$abilities}.entity_type", Morph::Everything);
                    $entityType === null
                        ? $query->orWhereNull("{$abilities}.entity_type")
                        : $query->orWhere("{$abilities}.entity_type", $entityType);
                })
                ->where(function (Builder $query) use ($abilities, $entityKey): void {
                    $query->whereNull("{$abilities}.entity_id");

                    if ($entityKey !== null) {
                        $query->orWhere("{$abilities}.entity_id", $entityKey);
                    }
                })
                ->where("{$abilities}.only_owned", false)
                ->where("{$permissions}.forbidden", $forbidden)
                ->where(function (Builder $query) use ($permissions, $ability): void {
                    $query
                        ->whereNull("{$permissions}.except")
                        ->orWhereJsonDoesntContain("{$permissions}.except", $ability);
                })
                ->tap(fn (QueryBuilder $query) => ScopeConstraint::apply($query, $permissions, $scope));
        };

        $grantedVia = function (Builder $query, bool $forbidden) use ($abilityMatches, $assignedRoles, $scope): void {
            $query
                ->whereHas('abilities', fn (Builder $query) => $abilityMatches($query, $forbidden))
                ->orWhereHas('roles', function (Builder $query) use ($abilityMatches, $assignedRoles, $scope, $forbidden): void {
                    $query
                        ->tap(fn (QueryBuilder $query) => ScopeConstraint::apply($query, $assignedRoles, $scope))
                        ->whereHas('abilities', fn (Builder $query) => $abilityMatches($query, $forbidden));
                });
        };

        return $query
            ->where(fn (Builder $query) => $grantedVia($query, false))
            ->whereNot(fn (Builder $query) => $grantedVia($query, true));
    }

    /**
     * Actors holding the role, globally or in the given scope.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function whereIs(Builder $query, string $role, ?Model $scope = null): Builder
    {
        return $query->whereHas('roles', function (Builder $query) use ($role, $scope): void {
            $query
                ->where(config()->string('valkyrie.tables.roles').'.name', $role)
                ->when($scope !== null, fn (Builder $query) => ScopeConstraint::apply($query, config()->string('valkyrie.tables.assigned_roles'), $scope));
        });
    }
}
