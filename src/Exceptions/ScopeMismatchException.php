<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Exceptions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Signl\Valkyrie\Models\Role;

final class ScopeMismatchException extends InvalidArgumentException
{
    public static function forName(string $name, Model $requested): self
    {
        return new self(
            "Role [{$name}] only has definitions for other scopes and none for {$requested->getMorphClass()}#{$requested->getKey()}.",
        );
    }

    public static function forRole(Role $role, ?Model $requested): self
    {
        $requestedScope = $requested === null
            ? 'no scope'
            : "{$requested->getMorphClass()}#{$requested->getKey()}";

        return new self(
            "Role [{$role->name}] is defined for scope {$role->scope_type}#{$role->scope_id} and cannot be assigned on {$requestedScope}.",
        );
    }
}
