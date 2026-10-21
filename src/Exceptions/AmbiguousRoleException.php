<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Exceptions;

use InvalidArgumentException;

final class AmbiguousRoleException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(
            "Role [{$name}] has several scoped definitions and no global one; pass on: \$scope to pick one.",
        );
    }
}
