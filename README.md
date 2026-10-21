<div align="center">
    <h1>Laravel Valkyrie</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/signlphp/laravel-valkyrie"><img src="https://img.shields.io/packagist/v/signlphp/laravel-valkyrie.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/signlphp/laravel-valkyrie"><img src="https://img.shields.io/packagist/php-v/signlphp/laravel-valkyrie.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/signlphp/laravel-valkyrie"><img src="https://img.shields.io/packagist/dependency-v/signlphp/laravel-valkyrie/illuminate%2Fcontracts.svg?style=flat-square&label=Laravel" alt="Laravel versions"></a>
    <a href="https://github.com/signlphp/laravel-valkyrie/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/signlphp/laravel-valkyrie/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/signlphp/laravel-valkyrie"><img src="https://img.shields.io/packagist/dt/signlphp/laravel-valkyrie.svg?style=flat-square" alt="Total Downloads"></a>
</p>

> [!WARNING]
> This package is pre-1.0 and still being battle-tested. The API may change in breaking ways between 0.x releases. Feedback and issue reports are very welcome.

Valkyrie is a roles-and-abilities (RBAC) package for Laravel with a fluent API. It stores authorization rules in the database, answers every Gate check through a `Gate::before` bridge, and adds a few things most RBAC packages leave out:

- **Forbids that always win** — an explicit forbid beats any allow, no matter where the allow comes from.
- **Scoped grants** — any grant or role assignment can be limited to one scope model (an office, a department, a project).
- **Ownership grants** — abilities that only apply to instances the actor owns.
- **Multi-tenancy ready, not multi-tenancy opinionated** — database-per-tenant setups work unchanged, a one-method cache partition keeps shared cache stores isolated, and single-database setups plug their own tenancy package into the extensible models. See [Multi-tenancy](#multi-tenancy).
- **A versioned cache** — resolved ability sets are cached per actor and scope. Invalidation bumps a version number instead of enumerating keys, is as precise as the write (a grant to one user never invalidates anyone else), and only becomes visible once the surrounding database transaction commits.
- **Extensible models** — subclass any Valkyrie model to add behavior; one config line swaps it in everywhere.

Looking for the full API reference? See the **[cheat sheet](CHEATSHEET.md)**.

## Table of contents

1. [Requirements & installation](#requirements--installation)
2. [Quickstart](#quickstart)
3. [Core concepts](#core-concepts)
4. [Configuration](#configuration)
5. [Granting and revoking abilities](#granting-and-revoking-abilities)
6. [Roles: assigning and checking](#roles-assigning-and-checking)
7. [Checking abilities](#checking-abilities)
8. [Middleware and Blade](#middleware-and-blade)
9. [Scopes](#scopes)
10. [Ownership](#ownership)
11. [Query scopes](#query-scopes)
12. [Multi-tenancy](#multi-tenancy)
13. [Extending the models](#extending-the-models)
14. [Caching](#caching)
15. [Events](#events)
16. [Console commands](#console-commands)
17. [Database schema](#database-schema)
18. [Known limitations](#known-limitations)
19. [Testing](#testing)

## Requirements & installation

Valkyrie requires **PHP 8.5+** and **Laravel 13**.

```bash
composer require signlphp/laravel-valkyrie
php artisan migrate
```

The service provider, the `Valkyrie` facade alias, and the migrations are auto-discovered. To customize the configuration, publish it:

```bash
php artisan vendor:publish --tag=laravel-valkyrie-config
```

### Customizing the schema

Migrations load from the package by default — renaming tables only requires the published config (`valkyrie.tables.*`), not the migration. If you need actual schema changes (for example adding a `tenant_id` column for single-database tenancy, or swapping the `json` columns for `text`), publish the migration and disable the package copy:

```bash
php artisan vendor:publish --tag=laravel-valkyrie-migrations
```

```php
// config/valkyrie.php
'migrations' => false,
```

The published file keeps its original filename, so the migration ledger stays consistent: in an app where the package migration already ran, the published copy is considered ran too and nothing executes twice.

## Quickstart

Add the trait to your user model:

```php
use Signl\Valkyrie\Concerns\HasRolesAndAbilities;

class User extends Authenticatable
{
    use HasRolesAndAbilities;
}
```

Grant, assign, and check:

```php
use Signl\Valkyrie\Facades\Valkyrie;

// Direct grants
$user->allow()->to('export-reports');
$user->allow()->to('view', Report::class);
$user->forbid()->to('delete', Report::class);

// Roles
$role = Valkyrie::role('manager', 'Manager');
Valkyrie::allow($role)->toManage(Report::class);
$user->assign('manager');

// Checks — everywhere Laravel authorization already works
$user->can('view', Report::class);        // true
Gate::allows('export-reports');           // true for the authenticated user
$user->isA('manager');                    // true
```

That's the whole loop: the Gate bridge detects the trait and starts answering `$user->can()`, `@can`, `->can()` route middleware, and policy-style checks for that model.

## Core concepts

| Term | Meaning |
|---|---|
| **Actor** | Any Eloquent model using `HasRolesAndAbilities` — typically a user, but a service account works just as well. Roles are actors too: abilities can be granted to a role. |
| **Ability** | Something an actor can be allowed or forbidden to do. Names are free-form strings; the database is the catalogue. An ability may be simple (no entity), tied to a model class, tied to one instance, or owner-only. |
| **Role** | A named bundle of permissions. A role definition may itself be scoped, making it a role for that scope only. |
| **Permission** | One allow or forbid row linking an ability to an entity (actor or role), optionally inside one scope. |
| **Scope** | Any Eloquent model a grant or role assignment is limited to. `null` means global. Matching is exact: a grant on a parent scope never applies to a child. |
| **Everything (`*`)** | The wildcard. An ability named `*` matches every ability name; an entity type of `*` matches every model. |

The resolution rule is simple and absolute: **any matching forbid returns `false`; otherwise a matching allow returns `true`; otherwise the answer is `null`** — and a `null` from Valkyrie falls through to your policies and gate definitions, so Valkyrie coexists with them instead of replacing them.

## Configuration

`config/valkyrie.php`:

| Key | Default | Purpose |
|---|---|---|
| `cache.enabled` | `env('VALKYRIE_CACHE', true)` | Toggles the cache store layer. The per-request memo always runs. |
| `cache.store` | `env('VALKYRIE_CACHE_STORE')` (null) | Cache store name; `null` means the application default. |
| `cache.ttl` | `86400` (24 h) | TTL backstop for cached ability rows; real invalidation is versioned. |
| `cache.partition` | `DefaultCachePartition::class` | Any class implementing `Contracts\CachePartition`; embedded in every cache key. See [Multi-tenancy](#multi-tenancy). |
| `migrations` | `true` | Load the package migrations. Set `false` after publishing them to take over the schema. |
| `models.*` | The package models | Swap in your own subclasses. See [Extending the models](#extending-the-models). |
| `tables.*` | `valkyrie_*` | Table names, read everywhere through config. |
| `morphs.role`, `morphs.ability` | `role`, `ability` | Morph aliases for Valkyrie's own models. **Do not change once rows exist** — permissions granted to roles store the alias in `entity_type`. |
| `superuser.role`, `superuser.title` | `system-admin` | The role granted by `valkyrie:grant-superuser`. |

## Granting and revoking abilities

`allow` and `forbid` create permission rows; `disallow` and `unforbid` remove them. All four are available on the facade (for any actor, including roles) and on the trait (for `$this`).

```php
// The grant-builder terminals
$user->allow()->to('export-reports');                    // one ability
$user->allow()->to(['view', 'update'], Report::class);   // several, on a model
$user->allow()->toManage(Report::class);                 // every ability on a model
$user->allow()->toManage(Report::class, except: ['delete']);
$user->allow()->toOwn(Report::class);                    // only instances they own
$user->allow()->toOwn(Report::class, only: ['view', 'update']);
$user->allow()->everything();                            // the superuser grant
$user->allow()->everything(except: ['impersonate']);

// Forbids beat allows, always
$user->forbid()->to('delete', Report::class);

// Removals mirror the grants
Valkyrie::disallow($user)->to('export-reports');        // remove an allow
Valkyrie::unforbid($user)->to('delete', Report::class); // remove a forbid
```

Every write runs in a transaction, refreshes the cache, and dispatches its event after commit. Grants are idempotent — repeating one changes nothing and dispatches nothing.

## Roles: assigning and checking

```php
Valkyrie::role('manager', 'Manager');          // find-or-create (also implicit on first assign)
$user->assign('manager');                       // give the role
$user->assign('manager', $office);              // ...inside one scope
$user->retract('manager');                      // take it away
Valkyrie::assign('manager', [$a, $b]);         // several actors at once

$user->isA('manager');                          // holds it anywhere
$user->isA('manager', $office);                 // globally, or in that scope
$user->isAll('manager', 'editor');
Valkyrie::holdsRole($user, 'manager');         // same check from the facade
```

Grant abilities to the role and every holder gets them:

```php
$role = Valkyrie::role('manager');
Valkyrie::allow($role)->toManage(Report::class);
```

## Checking abilities

Use Laravel's own authorization surface — it all flows through the Gate bridge:

```php
$user->can('view', $report);
$user->cannot('delete', $report);
Gate::allows('export-reports');
$this->authorize('update', $report);                    // in controllers
Route::put('/reports/{report}', ...)->can('update', 'report');
```

Or ask Valkyrie directly, which also lets you pass an explicit scope:

```php
Valkyrie::can($user, 'view', $report);
Valkyrie::can($user, 'approve', on: $office);          // check inside one scope
Valkyrie::check($user, 'view', $report);               // raw tri-state: true | false | null
```

## Middleware and Blade

Two route middleware ship registered and ready:

```php
Route::get('/admin', ...)->middleware('role:system-admin');         // any-of
Route::get('/posts', ...)->middleware('ability:view-posts,edit-posts');
```

A guest triggers an `AuthenticationException` (401 / login redirect, like `auth`); an authenticated user who fails the check triggers an `AuthorizationException` (403), flowing through your exception handler exactly like a policy denial. The `ability` middleware resolves through the Gate bridge, so forbids and policy fallthrough apply.

> [!WARNING]
> The two middleware treat [scopes](#scopes) differently. `role:` passes when the user holds the role **anywhere** — a role assigned inside one scope (`$user->assign('admin', $office)`) satisfies `role:admin` on any route. `ability:` goes through the Gate without a scope, so only **global** (scope-`null`) grants match. If you use scoped roles for per-organization access, do not protect cross-organization routes with `role:` middleware alone — check the role in its scope explicitly (`$user->isA('admin', $office)`) or gate the route on a globally granted ability instead.

In Blade, `@can` already works. For roles:

```blade
@role('manager')
    You manage things.
@else
    You do not.
@endrole

@unlessrole('manager')
    Shown to everyone without the role.
@endrole
```

## Scopes

Any grant or role assignment can be limited to one scope model:

```php
Valkyrie::allow($user, $office)->to('approve');   // only inside $office
$user->assign('manager', $office);                 // role held only in $office
Valkyrie::can($user, 'approve', on: $office);     // check inside $office
```

Scope matching is **exact**. A grant scoped to a parent office never applies to a child office, and a global check never sees scoped grants. Global grants, however, apply inside every scope.

A model can declare its own governing scope by implementing `ScopedEntity`:

```php
use Signl\Valkyrie\Contracts\ScopedEntity;

class Report extends Model implements ScopedEntity
{
    public function accessScope(): ?Model
    {
        return $this->office;
    }
}
```

When a check targets a `ScopedEntity` instance, its own scope wins over any explicit `on:` argument — `$user->can('view', $report)` automatically checks inside the report's office.

Role definitions can also be scoped: `Valkyrie::role('office-admin', scope: $office)` creates a definition that exists for that office only and forces its scope on every assignment. Assigning it elsewhere throws `ScopeMismatchException`; a name with several scoped definitions and no global one throws `AmbiguousRoleException` when assigned without a scope.

## Ownership

Owner-only grants apply exclusively to instances the actor owns:

```php
$user->allow()->toOwn(Report::class);              // every ability, on owned reports
$user->allow()->toOwn(Report::class, only: ['view', 'update']);
$user->allow()->toOwnEverything();                 // owned instances of every model
```

By default an actor owns a model when its `user_id` equals the actor's key. Register a different attribute or a closure per model:

```php
Valkyrie::ownedVia(Report::class, 'author_id');
Valkyrie::ownedVia(Invoice::class, fn (Invoice $invoice, Model $actor): bool => $invoice->team_id === $actor->team_id);
```

A model without the attribute is simply not owned — never an error.

## Query scopes

Find the actors that can do something, or hold a role:

```php
User::whereCan('view', Report::class)->get();   // can, and is not forbidden
User::whereCan('approve', scope: $office)->get();
User::whereIs('manager')->get();
User::whereIs('manager', $office)->get();
```

`whereCan` is a query-level approximation of the resolver; see [Known limitations](#known-limitations).

## Multi-tenancy

Valkyrie ships no tenancy layer of its own — row scoping belongs to your tenancy package. What it ships is the one piece your tenancy package cannot reach: a **cache partition** that keeps cached ability sets isolated when tenants share a cache store. Pick the recipe that matches your setup:

**Database-per-tenant** (for example stancl/tenancy in multi-database mode): works unchanged — the `valkyrie_*` tables live in each tenant database. The only requirement is cache isolation: actor id 1 exists in every tenant database, so if all tenants share one cache store, cached ability sets would collide across tenants. Configure the [cache partition](#the-cache-partition) below, use your tenancy package's per-tenant cache separation (such as stancl's cache bootstrapper), or disable the cache.

**Single database, per-organization access**: you may not need tenancy at all. [Scopes](#scopes) already limit grants and role assignments to one model (an organization, a team, a project), and a scoped role definition exists for that scope only.

**Single database, shared tables**: let your tenancy package scope the rows. All four models are [extensible](#extending-the-models): subclass them, add your tenancy package's trait (for example stancl's `BelongsToTenant`), register the subclasses under `valkyrie.models.*`, and publish the migration to add your `tenant_id` column — include it in the unique indexes on the abilities and roles tables so each tenant keeps its own catalogue. Configure the cache partition so cached ability sets stay per-tenant.

**Hybrid** (shared tables for standard plans, a dedicated database for enterprise plans): the two recipes above compose. Set up the extended models for the shared database; in a dedicated tenant database the trait's scoping is harmless, since every row there belongs to that tenant and always matches. Valkyrie's models use the default connection, so your tenancy package's connection switching routes them automatically — just run the published migration in each tenant database too. One cache partition returning the active tenant's key covers both plan types: actor id 1 exists in the shared database *and* in every dedicated one, so the partition is what keeps their cached ability sets apart.

### The cache partition

`Signl\Valkyrie\Contracts\CachePartition` is one method:

```php
interface CachePartition
{
    public function key(): string;
}
```

Every cache key and invalidation version embeds the partition. The default (`DefaultCachePartition`) returns a constant, which is correct whenever one application context owns the cache store. When tenants share a store, return the active tenant's key instead:

```php
final class TenantCachePartition implements CachePartition
{
    public function key(): string
    {
        return (string) (tenant()?->getTenantKey() ?? 'central');
    }
}
```

and point `valkyrie.cache.partition` at your class. It resolves through the container, so dependencies inject normally.

## Extending the models

All four models (`Role`, `Ability`, `Permission`, `AssignedRole`) are designed to be subclassed:

```php
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

```php
// config/valkyrie.php
'models' => [
    'role' => App\Models\Role::class,
    // ...
],
```

Everything — the manager, the grant builders, the trait's relations, the morph map, cache invalidation — resolves through this config. Laravel's class attributes (`#[Fillable]`, `#[ScopedBy]`, `#[UseFactory]`) are inherited from the base model, so a subclass only declares what it adds. A configured class that does not extend the base model throws an `InvalidArgumentException` immediately.

This is the intended seam for cross-cutting additions such as an audit package: subscribe to the [events](#events), or attach observers to your subclasses.

## Caching

Two layers answer repeated checks:

1. A **per-request memo** on the resolver singleton (always on). Queue workers and Octane get it flushed automatically at job/request boundaries.
2. A **versioned cache store** (`valkyrie.cache.*`). Every cached ability set is keyed by a partition version plus an actor version. Invalidation bumps the matching version — a direct grant to one user invalidates only that user; role and ability definition changes invalidate the whole partition; definition edits that cannot change resolution (a title, for example) do not bump at all.

Version bumps run **after the surrounding database transaction commits**, so other processes never cache uncommitted rules and a rolled-back grant never leaks. Within the writing process, new grants are visible immediately.

Writes that bypass the facade (admin UIs, tinker, factories, raw model saves) are covered by a safety net: model events on all four models trigger the same invalidation.

`Valkyrie::refresh()` invalidates everything on demand; `Valkyrie::refreshFor($actor)` invalidates one actor. Cache keys embed the configured [cache partition](#the-cache-partition), so tenants sharing one store never see each other's entries.

## Events

All events live in `Signl\Valkyrie\Events`, are dispatched **after commit**, and carry readonly properties — they are the integration point for audit trails, logging, and notifications:

| Event | When |
|---|---|
| `AbilityGranted($actor, $ability, $scope)` | An allow row was created. |
| `AbilityForbidden($actor, $ability, $scope)` | A forbid row was created. |
| `AbilityRevoked($actor, $ability, $scope, $wasForbidden)` | An allow (`wasForbidden: false`) or forbid (`true`) row was removed. |
| `RoleAssigned($actor, $role, $scope)` | A role assignment was created. |
| `RoleRetracted($actor, $role, $scope)` | A role assignment was removed. |

Idempotent repeats dispatch nothing.

## Console commands

```bash
php artisan valkyrie:grant-superuser admin@example.com
```

A lockout guard: finds the user by email on your configured auth user model, creates the superuser role (`valkyrie.superuser.role`, default `system-admin`), grants it `everything()`, and assigns it. Safe to run repeatedly.

## Database schema

Four tables, names configurable under `valkyrie.tables.*`.

| Table | Purpose | Unique key |
|---|---|---|
| `valkyrie_abilities` | Ability definitions (`name`, `title`, `entity_type/id`, `only_owned`, `options`) | `name, entity_type, entity_id, only_owned` |
| `valkyrie_roles` | Role definitions (`name`, `title`, `scope_type/id`) | `name, scope_type, scope_id` |
| `valkyrie_assigned_roles` | Role ↔ actor assignments, optionally scoped | `role_id, entity_type, entity_id, scope_type, scope_id` |
| `valkyrie_permissions` | Allow/forbid rows linking abilities to actors or roles, optionally scoped, with an `except` list on wildcards | `ability_id, entity_type, entity_id, forbidden, scope_type, scope_id` |

Deleting a role removes its assignments (database cascade) and its permission rows (model event — permissions point at roles by morph, not foreign key).

## Known limitations

- **`whereCan` excludes owner-only grants.** Ownership depends on the concrete instance, which a query over actors cannot know. An actor whose only path to an ability is a `toOwn` grant will not appear in `whereCan` results.
- **A long-lived process sees cross-process rule changes on its next request/job**, not mid-request — the per-request version memo trades that for one cache round-trip per check.

## Testing

The package ships its model factories for consuming apps:

```php
use Signl\Valkyrie\Models\Role;

$role = Role::factory()->scopedTo($office)->create();
Permission::factory()->grantedTo($user)->forbidden()->create();
AssignedRole::factory()->heldBy($user)->scopedTo($office)->create();
```

In your tests, grant through the facade and assert through the Gate — both run against the real resolver.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Valkyrie! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Kim Eric Helle](https://github.com/signlphp)
- [All Contributors](../../contributors)

### Inspiration

Valkyrie stands on the shoulders of two excellent packages that shaped how authorization is done in the Laravel ecosystem:

- [silber/bouncer](https://github.com/JosephSilber/bouncer) — the fluent grant API and database-backed abilities model were a big inspiration.
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) — the gold standard for roles and permissions in Laravel.

## License

Laravel Valkyrie is open-sourced software licensed under the [MIT license](LICENSE.md).
