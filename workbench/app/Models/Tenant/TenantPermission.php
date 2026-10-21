<?php

namespace Workbench\App\Models\Tenant;

use Signl\Valkyrie\Models\Permission;

class TenantPermission extends Permission
{
    protected $connection = 'tenant';
}
