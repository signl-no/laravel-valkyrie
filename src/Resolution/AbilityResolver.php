<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Resolution;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Signl\Valkyrie\Ownership\OwnershipResolver;
use Signl\Valkyrie\Support\Models;
use Signl\Valkyrie\Support\Morph;
use Signl\Valkyrie\Support\ScopeConstraint;

/**
 * Resolves direct and role-held grants to true, false (forbidden) or null (no opinion).
 *
 * @phpstan-import-type AbilityRow from AbilityCache
 */
#[Singleton]
final class AbilityResolver
{
    public function __construct(
        private readonly AbilityCache $cache,
        private readonly OwnershipResolver $ownership,
    ) {}

    /**
     * @param  Model|class-string<Model>|null  $entity
     */
    public function check(Model $actor, string $ability, Model|string|null $entity = null, ?Model $scope = null): ?bool
    {
        $effectiveScope = ScopeConstraint::effective($entity, $scope);
        $entityType = Morph::type($entity);
        $entityKey = Morph::key($entity);

        $rows = $this->cache->rows(
            actorType: $actor->getMorphClass(),
            actorKey: $actor->getKey(),
            scopeType: $effectiveScope?->getMorphClass(),
            scopeKey: $effectiveScope?->getKey(),
            load: fn (): array => $this->load($actor, $effectiveScope),
        );

        $allowed = false;

        foreach ($rows as $row) {
            if (! $this->matches($row, $ability, $entityType, $entityKey)) {
                continue;
            }

            if ($row['only_owned'] && ! ($entity instanceof Model && $this->ownership->owns($actor, $entity))) {
                continue;
            }

            if ($row['forbidden']) {
                return false;
            }

            $allowed = true;
        }

        return $allowed ? true : null;
    }

    /**
     * Every row reachable by the actor inside the scope, read unhydrated so it caches as arrays.
     *
     * @return list<AbilityRow>
     */
    private function load(Model $actor, ?Model $scope): array
    {
        $permissions = config()->string('valkyrie.tables.permissions');
        $abilities = config()->string('valkyrie.tables.abilities');
        $assignedRoles = config()->string('valkyrie.tables.assigned_roles');
        $roleAlias = Relation::getMorphAlias(Models::role());

        $heldRoleIds = Models::assignedRole()::query()
            ->where("{$assignedRoles}.entity_type", $actor->getMorphClass())
            ->where("{$assignedRoles}.entity_id", $actor->getKey())
            ->tap(fn (Builder $query) => ScopeConstraint::apply($query, $assignedRoles, $scope))
            ->select("{$assignedRoles}.role_id")
            ->toBase();

        $rows = Models::permission()::query()
            ->join($abilities, "{$abilities}.id", '=', "{$permissions}.ability_id")
            ->where(function (Builder $query) use ($actor, $roleAlias, $heldRoleIds, $permissions): void {
                $query
                    ->where(function (Builder $query) use ($actor, $permissions): void {
                        $query
                            ->where("{$permissions}.entity_type", $actor->getMorphClass())
                            ->where("{$permissions}.entity_id", $actor->getKey());
                    })
                    ->orWhere(function (Builder $query) use ($roleAlias, $heldRoleIds, $permissions): void {
                        $query
                            ->where("{$permissions}.entity_type", $roleAlias)
                            ->whereIn("{$permissions}.entity_id", $heldRoleIds);
                    });
            })
            ->tap(fn (Builder $query) => ScopeConstraint::apply($query, $permissions, $scope))
            ->toBase()
            ->get([
                "{$abilities}.name",
                "{$abilities}.entity_type",
                "{$abilities}.entity_id",
                "{$abilities}.only_owned",
                "{$permissions}.forbidden",
                "{$permissions}.except",
            ]);

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'name' => (string) $row->name,
                'entity_type' => $row->entity_type === null ? null : (string) $row->entity_type,
                'entity_id' => is_int($row->entity_id) || is_string($row->entity_id) ? $row->entity_id : null,
                'only_owned' => (bool) $row->only_owned,
                'forbidden' => (bool) $row->forbidden,
                'except' => is_string($row->except) ? array_values((array) json_decode($row->except, true)) : [],
            ];
        }

        return $result;
    }

    /**
     * @param  AbilityRow  $row
     */
    private function matches(array $row, string $ability, ?string $entityType, int|string|null $entityKey): bool
    {
        if ($row['name'] !== Morph::Everything && $row['name'] !== $ability) {
            return false;
        }

        if ($row['name'] === Morph::Everything && collect($row['except'])->contains($ability)) {
            return false;
        }

        if ($row['entity_type'] !== Morph::Everything && $row['entity_type'] !== $entityType) {
            return false;
        }

        if ($row['entity_id'] !== null && ($entityKey === null || (string) $row['entity_id'] !== (string) $entityKey)) {
            return false;
        }

        return true;
    }
}
