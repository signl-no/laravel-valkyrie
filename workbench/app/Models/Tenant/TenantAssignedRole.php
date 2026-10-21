<?php

namespace Workbench\App\Models\Tenant;

use Signl\Valkyrie\Models\AssignedRole;

class TenantAssignedRole extends AssignedRole
{
    protected $connection = 'tenant';
}
