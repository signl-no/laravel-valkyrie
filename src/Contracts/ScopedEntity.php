<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that knows which scope governs access to it; its scope wins over `$on`.
 */
interface ScopedEntity
{
    public function accessScope(): ?Model;
}
