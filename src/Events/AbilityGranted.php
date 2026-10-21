<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Signl\Valkyrie\Models\Ability;

final class AbilityGranted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Model $actor,
        public readonly Ability $ability,
        public readonly ?Model $scope = null,
    ) {}
}
