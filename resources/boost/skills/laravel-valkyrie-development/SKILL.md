---
name: laravel-valkyrie-development
description: >
  Configure and apply the Laravel Valkyrie RBAC package (roles, abilities,
  scopes, ownership) in Laravel applications, including multi-tenant setups.
license: MIT
metadata:
  author: Kim Eric Helle
---

# Laravel Valkyrie

Use this skill when a Laravel application needs to integrate the Laravel Valkyrie package: database-backed roles and abilities answered through the Gate, with forbids that beat allows, scoped grants, and ownership grants.

## Primary Goal

- apply the `signlphp/laravel-valkyrie` package's public API in the smallest correct way

## Workflow

### 1. Install and wire up

- `composer require signlphp/laravel-valkyrie` then `php artisan migrate` (creates the four `valkyrie_*` tables; provider and `Valkyrie` facade alias are auto-discovered)
- add `Signl\Valkyrie\Concerns\HasRolesAndAbilities` to each actor model (usually `User`); the Gate bridge activates automatically for models using the trait
- publish config only when a default must change: `php artisan vendor:publish --tag=laravel-valkyrie-config`
- schema changes (e.g. adding a `tenant_id` column): `php artisan vendor:publish --tag=laravel-valkyrie-migrations`, then set `valkyrie.migrations => false`; the published file keeps its original filename, so nothing runs twice. Table renames need only the config, never the migration.

### 2. Grant, assign, check

- grant: `$user->allow()->to('export-reports')`, `->to(['view','update'], Report::class)`, `->toManage(Report::class, except: [...])`, `->toOwn(Report::class, only: [...])`, `->everything()`
- forbid (always beats allows): `$user->forbid()->to('delete', Report::class)`; remove rows with `Valkyrie::disallow(...)` / `Valkyrie::unforbid(...)`
- roles: `Valkyrie::role('manager', 'Manager')`, `Valkyrie::allow($role)->toManage(Report::class)`, `$user->assign('manager')`, `$user->retract('manager')`
- check through Laravel's own surface — `$user->can(...)`, `Gate::allows(...)`, `@can`, `$this->authorize(...)`, route `->can(...)` — or explicitly via `Valkyrie::can($user, 'approve', on: $office)`; `Valkyrie::check()` returns the raw tri-state (`true|false|null`, null falls through to policies)
- role checks: `$user->isA('manager')`, `$user->isA('manager', $office)`, `Valkyrie::holdsRole($user, 'manager')`

### 3. Protect routes and views

- middleware (pre-registered aliases, any-of semantics): `->middleware('role:system-admin')`, `->middleware('ability:view-posts,edit-posts')`; guests get 401, denied users 403 via `AuthorizationException`
- scope caveat: `role:` passes when the user holds the role in *any* scope, while `ability:` matches only global (scope-`null`) grants — never protect cross-organization routes with `role:` alone when roles are assigned per scope; check `isA($role, $scope)` explicitly instead
- Blade: `@role('manager') ... @else ... @endrole`, `@unlessrole('manager') ... @endrole`; use the built-in `@can` for abilities

### 4. Scopes, ownership, tenancy (when needed)

- scope any grant/assignment to one model: `Valkyrie::allow($user, $office)->to('approve')`, `$user->assign('manager', $office)`; matching is exact, never inherited
- let an entity carry its own scope by implementing `Signl\Valkyrie\Contracts\ScopedEntity::accessScope()`
- ownership: `$user->allow()->toOwn(Report::class)`; default owner attribute is `user_id`, override via `Valkyrie::ownedVia(Report::class, 'author_id')` or a closure
- multi-tenancy: Valkyrie ships no tenancy layer — database-per-tenant works unchanged; single-database tenancy scopes the rows through extended models (add your tenancy package's trait, publish the migration for the `tenant_id` column). Whenever tenants share one cache store, set `valkyrie.cache.partition` to a class implementing `Contracts\CachePartition` that returns the active tenant's key, so cached ability sets never leak across tenants; see the README's Multi-tenancy recipes

### 5. Extend models (when needed)

- subclass `Signl\Valkyrie\Models\{Role,Ability,Permission,AssignedRole}` and register the subclass under `valkyrie.models.*`; class attributes (`#[Fillable]`, `#[UseFactory]`) are inherited, so only add what changes (e.g. `#[ObservedBy]` for auditing, or a tenancy trait)
- listen to the after-commit events in `Signl\Valkyrie\Events` (`AbilityGranted`, `AbilityForbidden`, `AbilityRevoked`, `RoleAssigned`, `RoleRetracted`) for audit/logging

### 6. Operate

- `php artisan valkyrie:grant-superuser {email}` — idempotent lockout guard granting the configured superuser role (`valkyrie.superuser.role`) with everything
- querying actors: `User::whereCan('view', Report::class)`, `User::whereIs('manager', $office)`; `whereCan` excludes owner-only grants by design
- cache is versioned and self-invalidating (commit-safe, per-actor precision); force with `Valkyrie::refresh()` or `Valkyrie::refreshFor($actor)`; toggle via `VALKYRIE_CACHE` env

## Rules, References, and Templates

Read before executing:

- `README.md` — concepts, recipes, configuration, limitations
- `CHEATSHEET.md` — every public method by class

## Examples

- gate an admin panel: `Valkyrie::role('system-admin')`, `Valkyrie::allow($role)->everything()`, `$user->assign('system-admin')`, protect routes with `->middleware('role:system-admin')`
- per-office managers: `$user->assign('manager', $office)` plus `Report implements ScopedEntity` returning its office — `$user->can('update', $report)` then checks inside the report's office automatically
- tests in a consuming app: create rules with the shipped factories (`Role::factory()->scopedTo($office)`, `Permission::factory()->grantedTo($user)->forbidden()`) or grant via the facade, then assert with `$user->can(...)`

## Anti-patterns

- do not write `valkyrie_*` rows by hand when the facade/trait API covers it; direct writes bypass events (a safety net still fixes the cache)
- do not change `valkyrie.morphs.*` once permission rows exist — role grants store the alias in `entity_type`
- do not configure a `valkyrie.models.*` class that does not extend the package base model (it throws)
- do not rely on `whereCan` to find actors whose only access is an owner-only grant
- do not document or use package internals (`AbilityResolver`, `AbilityCache`) from app code; the facade and trait are the public surface
