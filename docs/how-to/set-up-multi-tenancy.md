# Set up multi-tenancy

Use this guide to run Valkyrie in a multi-tenant application. Find your setup below and follow only that section.

| Your setup | Section |
|---|---|
| Each tenant has its own database | [Database per tenant](#database-per-tenant) |
| One database, and "tenants" are organizations inside your app | [Organizations in one database](#organizations-in-one-database) |
| One database, and a tenancy package scopes rows by `tenant_id` | [Shared tables](#shared-tables) |
| Shared tables for some tenants, dedicated databases for others | [Hybrid](#hybrid) |

## Database per tenant

The `valkyrie_*` tables live in each tenant's database, and Valkyrie's models use the default connection, so your tenancy package's connection switching routes them for you. Run Valkyrie's migration in each tenant database.

You don't need any cache configuration. Valkyrie includes the active connection's name and database name in every cache key, so actor ID `1` in one tenant database never shares cached rows with actor ID `1` in another. This also holds inside one process that switches tenants, such as a command that loops over tenants.

If your tenancy package keeps the default connection on a central database and switches a separate tenant connection, point Valkyrie's models at that connection with [model subclasses](extend-the-models.md) that set `$connection`. Valkyrie keys its cache by the connection the models use, so tenant switches stay isolated.

## Organizations in one database

If tenants are records in your own app (organizations, teams, workspaces), you may not need a tenancy package. [Scope grants and roles](scope-grants-to-a-model.md) to the organization model instead:

```php
$user->assign('admin', $organization);
Valkyrie::can($user, 'invite-members', on: $organization);
```

You don't need any cache configuration, because the actor and scope keys are already unique.

## Shared tables

Let your tenancy package scope Valkyrie's rows the same way it scopes your own.

1. Publish the migration and stop the package from loading its own copy:

   ```bash
   php artisan vendor:publish --tag=laravel-valkyrie-migrations
   ```

   ```php
   // config/valkyrie.php
   'migrations' => false,
   ```

2. Add a `tenant_id` column to all four tables in the published migration. Add it to the unique indexes on the abilities and roles tables too, so each tenant keeps its own catalog:

   ```php
   $table->unique(['tenant_id', 'name', 'entity_type', 'entity_id', 'only_owned'], "{$abilities}_unique");
   // ...
   $table->unique(['tenant_id', 'name', 'scope_type', 'scope_id'], "{$roles}_unique");
   ```

3. Subclass each model and add your tenancy package's trait:

   ```php
   namespace App\Models\Valkyrie;

   use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

   class Role extends \Signl\Valkyrie\Models\Role
   {
       use BelongsToTenant;
   }
   ```

   Do the same for `Ability`, `Permission` and `AssignedRole`.

4. Register the subclasses:

   ```php
   // config/valkyrie.php
   'models' => [
       'role' => App\Models\Valkyrie\Role::class,
       'ability' => App\Models\Valkyrie\Ability::class,
       'permission' => App\Models\Valkyrie\Permission::class,
       'assigned_role' => App\Models\Valkyrie\AssignedRole::class,
   ],
   ```

5. [Add a cache partition](#add-a-cache-partition). All tenants share one database here, so the partition is the only thing that keeps their cached ability sets apart.

For more on subclassing, see [Extend the models](extend-the-models.md).

## Hybrid

Follow [Shared tables](#shared-tables) in full. Then run the published migration in each dedicated tenant database as well. Inside a dedicated database, the tenancy trait's scoping does no harm, because every row there belongs to that tenant.

The cache partition from step 5 covers the shared database. Dedicated databases are already kept apart by database name.

## Add a cache partition

You need a partition when tenants share **one database** and **one cache store**.

1. Write a class that returns the active tenant's key:

   ```php
   namespace App\Support;

   use Signl\Valkyrie\Contracts\CachePartition;

   final class TenantCachePartition implements CachePartition
   {
       public function key(): string
       {
           return (string) (tenant()?->getTenantKey() ?? 'central');
       }
   }
   ```

   Valkyrie resolves the class through the container, so you can inject dependencies in its constructor.

2. Point the config at it:

   ```php
   // config/valkyrie.php
   'cache' => [
       // ...
       'partition' => App\Support\TenantCachePartition::class,
   ],
   ```

## Verify

In two tenants, create a user with the same ID and grant an ability to only one of them. Run `$user->can(...)` in each tenant: only the user with the grant should pass, even when the cache is warm.

## Related

- [Multi-tenancy design](../explanation/multi-tenancy-design.md) explains why Valkyrie ships no tenancy layer.
- [Caching](../explanation/caching.md) explains how the partition fits into cache keys.
