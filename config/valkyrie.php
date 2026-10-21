<?php

declare(strict_types=1);

use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\Resolution\DefaultCachePartition;

return [

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Resolved ability rows are cached per actor in the given store. Invalidation
    | is versioned, so the ttl is only a backstop.
    |
    | The partition (any class implementing Contracts\CachePartition) is embedded
    | in every cache key. Point it at your tenant key when multiple tenants share
    | one cache store; the default is correct when one application owns the store.
    |
    */

    'cache' => [
        'enabled' => (bool) env('VALKYRIE_CACHE', true),
        'store' => env('VALKYRIE_CACHE_STORE'),
        'ttl' => 60 * 60 * 24,
        'partition' => DefaultCachePartition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | The package loads its own migrations by default. To customize the schema,
    | publish them (--tag=laravel-valkyrie-migrations) and set this to false;
    | the published file keeps its original name, so the migration ledger stays
    | consistent and nothing runs twice.
    |
    */

    'migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap in your own subclasses of the package models to add behavior.
    |
    */

    'models' => [
        'role' => Role::class,
        'ability' => Ability::class,
        'permission' => Permission::class,
        'assigned_role' => AssignedRole::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'abilities' => 'valkyrie_abilities',
        'roles' => 'valkyrie_roles',
        'assigned_roles' => 'valkyrie_assigned_roles',
        'permissions' => 'valkyrie_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph aliases
    |--------------------------------------------------------------------------
    |
    | Stored in entity_type columns when permissions target roles or abilities.
    | Do not change these once rows exist.
    |
    */

    'morphs' => [
        'role' => 'role',
        'ability' => 'ability',
    ],

    /*
    |--------------------------------------------------------------------------
    | Superuser
    |--------------------------------------------------------------------------
    |
    | The role granted by valkyrie:grant-superuser.
    |
    */

    'superuser' => [
        'role' => 'system-admin',
        'title' => 'System Administrator',
    ],

];
