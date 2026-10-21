<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Signl\Valkyrie\Models\Role;
use Workbench\App\Observers\CustomRoleObserver;

#[ObservedBy([CustomRoleObserver::class])]
class CustomRole extends Role
{
    public function isSystemRole(): bool
    {
        return str_starts_with($this->name, 'system-');
    }
}
