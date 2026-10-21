# Extend the models

Use this guide to add behavior to Valkyrie's models, such as observers, global scopes, extra relations or helper methods.

## 1. Subclass the model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Signl\Valkyrie\Models\Role as BaseRole;

#[ObservedBy([RoleAuditObserver::class])]
class Role extends BaseRole
{
    public function isSystemRole(): bool
    {
        return str_starts_with($this->name, 'system-');
    }
}
```

Laravel's class attributes on the base model, such as `#[Fillable]` and `#[UseFactory]`, are inherited. Declare only what you add.

You can extend any of the four models: `Role`, `Ability`, `Permission` and `AssignedRole`.

## 2. Register the subclass

```php
// config/valkyrie.php
'models' => [
    'role' => App\Models\Role::class,
    'ability' => Signl\Valkyrie\Models\Ability::class,
    'permission' => Signl\Valkyrie\Models\Permission::class,
    'assigned_role' => Signl\Valkyrie\Models\AssignedRole::class,
],
```

Valkyrie resolves every model through this config: the manager, the grant builders, the trait's relations, the morph map and cache invalidation. Your subclass is used everywhere.

## Verify

```php
Valkyrie::role('editor') instanceof App\Models\Role;   // true
```

If the configured class doesn't extend the matching base model, the first call that resolves it throws `InvalidArgumentException`.

## Audit trails and other cross-cutting concerns

There are two ways to hook in:

- **Events** fire once the transaction commits, and only for real changes. Use them to record grant and role changes. See [Events](../reference/events.md).
- **Observers on your subclasses** see every write, including raw model saves from admin screens or seeders.

Writing to the models directly is safe: model events on all four models invalidate the cache. See [Caching](../explanation/caching.md).

## Related

- [Set up multi-tenancy](set-up-multi-tenancy.md) uses this technique to add a tenancy trait.
