<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Grants;

enum GrantMode
{
    case Allow;
    case Disallow;
    case Forbid;
    case Unforbid;

    /**
     * Whether this mode creates rows (as opposed to removing them).
     */
    public function creates(): bool
    {
        return match ($this) {
            self::Allow, self::Forbid => true,
            self::Disallow, self::Unforbid => false,
        };
    }

    /**
     * Whether the permission rows this mode touches are forbid rows.
     */
    public function forbidden(): bool
    {
        return match ($this) {
            self::Forbid, self::Unforbid => true,
            self::Allow, self::Disallow => false,
        };
    }
}
