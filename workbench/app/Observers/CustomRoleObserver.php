<?php

namespace Workbench\App\Observers;

use Workbench\App\Models\CustomRole;

class CustomRoleObserver
{
    /** @var list<string> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function created(CustomRole $role): void
    {
        self::$events[] = "created:{$role->name}";
    }

    public function deleted(CustomRole $role): void
    {
        self::$events[] = "deleted:{$role->name}";
    }
}
