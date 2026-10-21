<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that knows which scope governs access to it; its scope replaces `$on`, and null means global.
 */
interface ScopedEntity
{
    public function accessScope(): ?Model;
}
