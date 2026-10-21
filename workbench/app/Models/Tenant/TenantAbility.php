<?php

namespace Workbench\App\Models\Tenant;

use Signl\Valkyrie\Models\Ability;

class TenantAbility extends Ability
{
    protected $connection = 'tenant';
}
