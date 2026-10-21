<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Assignments;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Signl\Valkyrie\Events\RoleAssigned;
use Signl\Valkyrie\Events\RoleRetracted;
use Signl\Valkyrie\Exceptions\AmbiguousRoleException;
use Signl\Valkyrie\Exceptions\ScopeMismatchException;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\Support\Models;
use Signl\Valkyrie\Support\ScopeConstraint;
use Signl\Valkyrie\ValkyrieManager;

/**
 * Writes and removes role assignments. A name resolves to the definition for the
 * requested scope, else the global one; a scoped definition forces its scope.
 */
final class RoleAssignment
{
    public function __construct(private readonly ValkyrieManager $manager) {}

    /**
     * @param  list<Model>  $actors
     */
    public function assign(string|Role $role, array $actors, ?Model $scope): void
    {
        $definition = $this->resolveRole($role, $scope, createWhenMissing: true);

        if ($definition === null) {
            return;
        }

        $scope = $this->effectiveScope($definition, $scope);

        foreach ($actors as $actor) {
            $this->write($definition, $actor, $scope);
            $this->manager->refreshFor($actor);
        }
    }

    /**
     * @param  list<Model>  $actors
     */
    public function retract(string|Role $role, array $actors, ?Model $scope): void
    {
        $definition = $this->resolveRole($role, $scope, createWhenMissing: false);

        if ($definition === null) {
            return;
        }

        $scope = $this->effectiveScope($definition, $scope);

        foreach ($actors as $actor) {
            $this->remove($definition, $actor, $scope);
            $this->manager->refreshFor($actor);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Role $role, Model $actor, ?Model $scope): array
    {
        return [
            'role_id' => $role->getKey(),
            'entity_type' => $actor->getMorphClass(),
            'entity_id' => $actor->getKey(),
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->getKey(),
        ];
    }

    /**
     * A scoped definition implies its scope; an unscoped one takes the requested scope.
     */
    private function effectiveScope(Role $role, ?Model $scope): ?Model
    {
        if (! $role->isScoped()) {
            return $scope;
        }

        if ($scope !== null && ! ScopeConstraint::matches($role->scope_type, $role->scope_id, $scope)) {
            throw ScopeMismatchException::forRole($role, $scope);
        }

        return $scope ?? $role->scope;
    }

    private function remove(Role $role, Model $actor, ?Model $scope): void
    {
        DB::transaction(function () use ($role, $actor, $scope): void {
            $assignment = Models::assignedRole()::query()->where($this->attributes($role, $actor, $scope))->first();

            if ($assignment === null) {
                return;
            }

            $assignment->delete();

            RoleRetracted::dispatch($actor, $role, $scope);
        });
    }

    private function resolveRole(string|Role $role, ?Model $scope, bool $createWhenMissing): ?Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        /** @var Collection<int, Role> $definitions */
        $definitions = Models::role()::query()->where('name', $role)->get();
        $global = $definitions->first(fn (Role $definition): bool => ! $definition->isScoped());
        $scoped = $definitions->filter(fn (Role $definition): bool => $definition->isScoped());

        if ($scope !== null) {
            $match = $scoped->first(fn (Role $definition): bool => ScopeConstraint::matches($definition->scope_type, $definition->scope_id, $scope));

            if ($match !== null) {
                return $match;
            }

            if ($global !== null) {
                return $global;
            }

            throw_if($scoped->isNotEmpty(), ScopeMismatchException::forName($role, $scope));

            return $createWhenMissing ? $this->manager->role($role) : null;
        }

        if ($global !== null) {
            return $global;
        }

        if ($scoped->count() === 1) {
            return $scoped->first();
        }

        throw_if($scoped->count() > 1, AmbiguousRoleException::forName($role));

        return $createWhenMissing ? $this->manager->role($role) : null;
    }

    private function write(Role $role, Model $actor, ?Model $scope): void
    {
        DB::transaction(function () use ($role, $actor, $scope): void {
            $assignment = Models::assignedRole()::query()->firstOrCreate($this->attributes($role, $actor, $scope));

            if (! $assignment->wasRecentlyCreated) {
                return;
            }

            RoleAssigned::dispatch($actor, $role, $scope);
        });
    }
}
