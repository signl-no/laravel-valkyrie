<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Support;

use InvalidArgumentException;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Models\Role;

/**
 * Resolves the model classes configured under valkyrie.models.
 */
final class Models
{
    /**
     * @return class-string<Ability>
     */
    public static function ability(): string
    {
        return self::resolve('ability', Ability::class);
    }

    /**
     * @return class-string<AssignedRole>
     */
    public static function assignedRole(): string
    {
        return self::resolve('assigned_role', AssignedRole::class);
    }

    /**
     * @return class-string<Permission>
     */
    public static function permission(): string
    {
        return self::resolve('permission', Permission::class);
    }

    /**
     * @return class-string<Role>
     */
    public static function role(): string
    {
        return self::resolve('role', Role::class);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $base
     * @return class-string<TModel>
     */
    private static function resolve(string $key, string $base): string
    {
        $class = config()->string("valkyrie.models.{$key}", $base);

        throw_unless(
            is_a($class, $base, true),
            InvalidArgumentException::class,
            "Config [valkyrie.models.{$key}] must name a class extending [{$base}], got [{$class}].",
        );

        return $class;
    }
}
