<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Grants;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Signl\Valkyrie\Events\AbilityForbidden;
use Signl\Valkyrie\Events\AbilityGranted;
use Signl\Valkyrie\Events\AbilityRevoked;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Support\Models;
use Signl\Valkyrie\Support\Morph;
use Signl\Valkyrie\ValkyrieManager;

/**
 * The allow / forbid / disallow / unforbid chain for users, service accounts or roles.
 * Wildcard grants accept an `except` list that lives on, and dies with, that row.
 */
final class GrantBuilder
{
    /**
     * @param  Model|null  $scope  Scope the grant is limited to; null means global.
     */
    public function __construct(
        private readonly GrantMode $mode,
        private readonly Model $actor,
        private readonly ?Model $scope,
        private readonly ValkyrieManager $manager,
    ) {}

    /**
     * Every ability on every model, minus `except`: the superuser grant.
     *
     * @param  list<string>  $except
     * @return Collection<int, Permission>
     */
    public function everything(array $except = []): Collection
    {
        return $this->apply(collect([$this->resolve(Morph::Everything, Morph::Everything)]), $except);
    }

    /**
     * One or more abilities: simple (no entity), on a model class, or on one instance.
     *
     * @param  string|list<string>  $abilities
     * @param  Model|class-string<Model>|string|null  $entity
     * @return Collection<int, Permission>
     */
    public function to(string|array $abilities, Model|string|null $entity = null): Collection
    {
        return $this->apply(
            collect((array) $abilities)->map(fn (string $ability): ?Ability => $this->resolve($ability, $entity)),
        );
    }

    /**
     * Every ability on the model, minus `except`.
     *
     * @param  class-string<Model>|string  $model
     * @param  list<string>  $except
     * @return Collection<int, Permission>
     */
    public function toManage(string $model, array $except = []): Collection
    {
        return $this->apply(collect([$this->resolve(Morph::Everything, $model)]), $except);
    }

    /**
     * Abilities on instances the actor owns: every ability minus `except`, or just `only`.
     *
     * @param  class-string<Model>|string  $model
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @return Collection<int, Permission>
     */
    public function toOwn(string $model, ?array $only = null, array $except = []): Collection
    {
        throw_if($only !== null && $except !== [], InvalidArgumentException::class, 'Pass either only or except to toOwn(), not both.');

        $names = $only ?? [Morph::Everything];

        return $this->apply(
            collect($names)->map(fn (string $ability): ?Ability => $this->resolve($ability, $model, onlyOwned: true)),
            $only === null ? $except : [],
        );
    }

    /**
     * @param  list<string>  $except
     * @return Collection<int, Permission>
     */
    public function toOwnEverything(array $except = []): Collection
    {
        return $this->toOwn(Morph::Everything, except: $except);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ability|null>  $abilities
     * @param  list<string>  $except
     * @return Collection<int, Permission>
     */
    private function apply(\Illuminate\Support\Collection $abilities, array $except = []): Collection
    {
        $except = array_values(array_unique($except));
        sort($except);

        $permissions = DB::transaction(fn (): Collection => new Collection(
            $abilities
                ->filter()
                ->map(fn (Ability $ability): ?Permission => $this->mode->creates()
                    ? $this->create($ability, $except)
                    : $this->remove($ability))
                ->filter()
                ->values()
                ->all(),
        ));

        $this->manager->refreshFor($this->actor);

        return $permissions;
    }

    /**
     * @param  list<string>  $except
     */
    private function create(Ability $ability, array $except): Permission
    {
        $permission = Models::permission()::query()->firstOrCreate($this->identity($ability), ['except' => $except === [] ? null : $except]);

        if (! $permission->wasRecentlyCreated) {
            $permission->except = $except === [] ? null : $except;

            if (! $permission->isDirty()) {
                return $permission;
            }

            $permission->save();
        }

        $this->mode === GrantMode::Allow
            ? AbilityGranted::dispatch($this->actor, $ability, $this->scope)
            : AbilityForbidden::dispatch($this->actor, $ability, $this->scope);

        return $permission;
    }

    /**
     * The columns that identify a permission row; `except` is payload, not identity.
     *
     * @return array<string, mixed>
     */
    private function identity(Ability $ability): array
    {
        return [
            'ability_id' => $ability->getKey(),
            'entity_type' => $this->actor->getMorphClass(),
            'entity_id' => $this->actor->getKey(),
            'forbidden' => $this->mode->forbidden(),
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
        ];
    }

    /**
     * Grants find or create the ability; removals only look it up, so removing an
     * unknown ability never writes a definition row or invalidates the cache.
     *
     * @param  Model|class-string<Model>|string|null  $entity
     */
    private function resolve(string $name, Model|string|null $entity, bool $onlyOwned = false): ?Ability
    {
        if ($this->mode->creates()) {
            return $this->manager->ability($name, $entity, $onlyOwned);
        }

        return Models::ability()::query()
            ->where('name', $name)
            ->where('entity_type', Morph::type($entity))
            ->where('entity_id', Morph::key($entity))
            ->where('only_owned', $onlyOwned)
            ->first();
    }

    private function remove(Ability $ability): null
    {
        $permission = Models::permission()::query()->where($this->identity($ability))->first();

        if ($permission === null) {
            return null;
        }

        $permission->delete();

        AbilityRevoked::dispatch($this->actor, $ability, $this->scope, $this->mode->forbidden());

        return null;
    }
}
