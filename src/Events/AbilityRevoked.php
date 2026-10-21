<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Signl\Valkyrie\Models\Ability;

/**
 * Dispatched by both disallow (an allow row removed) and unforbid (a forbid row removed).
 */
final class AbilityRevoked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Model $actor,
        public readonly Ability $ability,
        public readonly ?Model $scope = null,
        public readonly bool $wasForbidden = false,
    ) {}
}
