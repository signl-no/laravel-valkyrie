<?php

namespace Workbench\App\Models\Tenant;

use Signl\Valkyrie\Models\Role;

class TenantRole extends Role
{
    protected $connection = 'tenant';
}
