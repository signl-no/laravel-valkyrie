# Test authorization in your app

Use this guide to write tests for your application's access rules with Valkyrie.

**Before you start:** your tests refresh the database between tests, for example with `RefreshDatabase`.

## Grant through the facade, check through the Gate

Set up access with the same API your app uses, then check it through Laravel's authorization. Both sides run against the real resolver:

```php
use Signl\Valkyrie\Facades\Valkyrie;

it('lets editors update reports', function () {
    $user = User::factory()->create();
    Valkyrie::allow(Valkyrie::role('editor'))->toManage(Report::class);
    $user->assign('editor');

    expect($user->can('update', Report::factory()->create()))->toBeTrue();
});
```

## Test HTTP access

```php
it('forbids non-managers from the admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();

    $user->assign('manager');

    $this->actingAs($user)->get('/admin')->assertOk();
});
```

## Build rows with the shipped factories

When you need rows in a particular shape without going through the grant API, use the package factories:

```php
use Signl\Valkyrie\Models\{Ability, AssignedRole, Permission, Role};

$role = Role::factory()->scopedTo($office)->create();

AssignedRole::factory()->for($role)->heldBy($user)->scopedTo($office)->create();

Permission::factory()
    ->for(Ability::factory()->state(['name' => 'delete']))
    ->grantedTo($user)
    ->forbidden()
    ->create();
```

| Factory | States |
|---|---|
| `Role::factory()` | `scopedTo(Model $scope)` |
| `Ability::factory()` | none (set `name`, `entity_type`, `entity_id` and `only_owned` with `state()`) |
| `AssignedRole::factory()` | `heldBy(Model $actor)`, `scopedTo(Model $scope)` |
| `Permission::factory()` | `grantedTo(Model $entity)`, `forbidden()`, `except(array $names)`, `scopedTo(Model $scope)` |

Rows created by factories invalidate the cache like any other write, so later checks see them.

## Assert events

```php
use Illuminate\Support\Facades\Event;
use Signl\Valkyrie\Events\RoleAssigned;

Event::fake([RoleAssigned::class]);

$user->assign('manager');

Event::assertDispatched(RoleAssigned::class, fn ($event) => $event->role->name === 'manager');
```

Fake only the Valkyrie events you assert on, so the rest of your app's listeners keep running.

## Register ownership rules where tests can see them

`Valkyrie::ownedVia()` registrations are kept in memory. Put them in a service provider's `boot()` method, not in a single request path, so every test application picks them up.

## Related

- [Events reference](../reference/events.md)
- [Database schema reference](../reference/database-schema.md)
