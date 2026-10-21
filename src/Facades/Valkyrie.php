<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Facades;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Signl\Valkyrie\Grants\GrantBuilder;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\ValkyrieManager;

/**
 * @method static GrantBuilder allow(Model $actor, ?Model $on = null)
 * @method static GrantBuilder forbid(Model $actor, ?Model $on = null)
 * @method static GrantBuilder disallow(Model $actor, ?Model $on = null)
 * @method static GrantBuilder unforbid(Model $actor, ?Model $on = null)
 * @method static void assign(string|Role $role, Model|array<int, Model> $actors, ?Model $on = null)
 * @method static void retract(string|Role $role, Model|array<int, Model> $actors, ?Model $on = null)
 * @method static Role role(string $name, ?string $title = null, ?Model $scope = null)
 * @method static Ability ability(string $name, Model|string|null $entity = null, bool $onlyOwned = false, ?string $title = null)
 * @method static bool can(Model $actor, string $ability, Model|string|null $entity = null, ?Model $on = null)
 * @method static bool cannot(Model $actor, string $ability, Model|string|null $entity = null, ?Model $on = null)
 * @method static bool|null check(Model $actor, string $ability, Model|string|null $entity = null, ?Model $on = null)
 * @method static bool holdsRole(Model $actor, string $role, ?Model $scope = null)
 * @method static void ownedVia(string $model, string|Closure $attribute)
 * @method static bool owns(Model $actor, Model $entity)
 * @method static void refresh()
 * @method static void refreshFor(Model $actor)
 *
 * @see ValkyrieManager
 */
final class Valkyrie extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ValkyrieManager::class;
    }
}
