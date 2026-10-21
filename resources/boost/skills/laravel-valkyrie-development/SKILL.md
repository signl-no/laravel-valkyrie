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

- apply the `signl-no/laravel-valkyrie` package's public API in the smallest correct way

## Workflow

### 1. Install and wire up

- `composer require signl-no/laravel-valkyrie` then `php artisan migrate` (creates the four `valkyrie_*` tables; provider and `Valkyrie` facade alias are auto-discovered)
- add `Signl\Valkyrie\Concerns\HasRolesAndAbilities` to each actor model (usually `User`); the Gate bridge activates automatically for models using the trait
- publish config only when a default must change: `php artisan vendor:publish --tag=laravel-valkyrie-config`
- schema changes (e.g. adding a `tenant_id` column): `php artisan vendor:publish --tag=laravel-valkyrie-migrations`, then set `valkyrie.migrations => false`; the published file keeps its original filename, so nothing runs twice. Table renames need only the config, never the migration.

### 2. Grant, assign, check

- grant: `$user->allow()->to('export-reports')`, `->to(['view','update'], Report::class)`, `->toManage(Report::class, except: [...])`, `->toOwn(Report::class, only: [...])`, `->everything()`
- forbid (always beats allows): `$user->forbid()->to('delete', Report::class)`; remove rows with `Valkyrie::disallow(...)` / `Valkyrie::unforbid(...)` (removing an unknown ability is a no-op)
- roles: `Valkyrie::role('manager', 'Manager')`, `Valkyrie::allow($role)->toManage(Report::class)`, `$user->assign('manager')`, `$user->retract('manager')`
- check through Laravel's own surface — `$user->can(...)`, `Gate::allows(...)`, `@can`, `$this->authorize(...)`, route `->can(...)` — or explicitly via `Valkyrie::can($user, 'approve', on: $office)`; `Valkyrie::check()` returns the raw tri-state (`true|false|null`, null falls through to policies)
- role checks: `$user->isA('manager')` (any scope), `$user->isA('manager', $office)` (globally or in that scope), `Valkyrie::holdsGlobalRole($user, 'manager')` (global assignments only)

### 3. Protect routes and views

- middleware (pre-registered aliases, any-of semantics): `->middleware('role:system-admin')`, `->middleware('ability:view-posts,edit-posts')`; guests get 401, denied users 403 via `AuthorizationException`
- scope caveat: `role:` counts only global assignments; a trailing `any` (`role:manager,any`) also accepts assignments in any scope — never protect cross-organization routes with `role:...,any` when roles are assigned per scope; check `isA($role, $scope)` explicitly instead. `ability:` matches only global (scope-`null`) grants
- Blade: `@role('manager') ... @else ... @endrole` (global assignments only; `@role('manager', $office)` checks globally or in that scope, `@role('manager', 'any')` any scope), `@unlessrole('manager') ... @endrole`; use the built-in `@can` for abilities

### 4. Scopes, ownership, tenancy (when needed)

- scope any grant/assignment to one model: `Valkyrie::allow($user, $office)->to('approve')`, `$user->assign('manager', $office)`; matching is exact, never inherited
- let an entity carry its own scope by implementing `Signl\Valkyrie\Contracts\ScopedEntity::accessScope()`; it always replaces `on:`, and `null` means global
- ownership: `$user->allow()->toOwn(Report::class)`; default owner attribute is `user_id`, override via `Valkyrie::ownedVia(Report::class, 'author_id')` or a closure. Attribute rules only match actors of the auth user model; other actor types (service accounts) need a closure
- deleting an actor removes its grants and role assignments (on `forceDelete()` for soft-deleting models); add `Signl\Valkyrie\Concerns\IsAccessScope` to scope models so deleting one removes everything scoped to it
- multi-tenancy: Valkyrie ships no tenancy layer.
  - database-per-tenant works unchanged; cache keys include the name and database of the connection the models use, so no cache configuration is needed. If the default connection stays central while a separate tenant connection switches, point the models at it with subclasses that set `$connection`
  - single database, shared tables: scope rows through extended models (add your tenancy package's trait, publish the migration for the `tenant_id` column) and set `valkyrie.cache.partition` to a class implementing `Signl\Valkyrie\Contracts\CachePartition` that returns the active tenant's key
  - organizations inside one app: use scopes instead of a tenancy package

### 5. Extend models (when needed)

- subclass `Signl\Valkyrie\Models\{Role,Ability,Permission,AssignedRole}` and register the subclass under `valkyrie.models.*`; class attributes (`#[Fillable]`, `#[UseFactory]`) are inherited, so only add what changes (e.g. `#[ObservedBy]` for auditing, or a tenancy trait)
- listen to the after-commit events in `Signl\Valkyrie\Events` (`AbilityGranted`, `AbilityForbidden`, `AbilityRevoked`, `RoleAssigned`, `RoleRetracted`) for audit/logging

### 6. Operate

- `php artisan valkyrie:grant-superuser {email}` — idempotent lockout guard granting the configured superuser role (`valkyrie.superuser.role`) with everything
- querying actors: `User::whereCan('view', Report::class)`, `User::whereIs('manager', $office)`; `whereCan` excludes owner-only grants by design
- cache is versioned and self-invalidating (commit-safe, per-actor precision, rollback-safe); model writes outside the facade are covered by model events; after mass queries on `valkyrie_*` tables call `Valkyrie::refresh()`; `Valkyrie::refreshFor($actor)` invalidates one actor; toggle the store layer via `VALKYRIE_CACHE` env

## Rules, References, and Templates

Read before executing:

Paths are inside the installed package (`vendor/signl-no/laravel-valkyrie/`):

- `CHEATSHEET.md` — one-page summary of setup, checks and which rule wins
- `docs/reference/api.md` — every public method by class; `docs/reference/configuration.md` — config keys, env vars, publish tags
- `docs/how-to/` — task recipes: scopes, ownership, routes and views, querying actors, multi-tenancy, schema, extending models, testing
- `docs/explanation/rule-resolution.md` — forbid-wins resolution, entity and scope matching
- `docs/explanation/known-limitations.md` — deliberate trade-offs and workarounds

## Examples

- gate an admin panel: `Valkyrie::role('system-admin')`, `Valkyrie::allow($role)->everything()`, `$user->assign('system-admin')`, protect routes with `->middleware('role:system-admin')`
- per-office managers: `$user->assign('manager', $office)` plus `Report implements ScopedEntity` returning its office — `$user->can('update', $report)` then checks inside the report's office automatically; add `IsAccessScope` to `Office` so deleting an office removes its scoped rules
- service-account ownership: `Valkyrie::ownedVia(Job::class, fn (Job $job, Model $actor): bool => $actor instanceof ServiceAccount && $job->service_account_id === $actor->getKey())`
- tests in a consuming app: create rules with the shipped factories (`Role::factory()->scopedTo($office)`, `Permission::factory()->grantedTo($user)->forbidden()`) or grant via the facade, then assert with `$user->can(...)`

## Anti-patterns

- do not write `valkyrie_*` rows by hand when the facade/trait API covers it; direct model writes bypass events (a safety net still fixes the cache), and mass queries bypass both
- do not change `valkyrie.morphs.*` once permission rows exist — role grants store the alias in `entity_type`
- do not configure a `valkyrie.models.*` class that does not extend the package base model (it throws)
- do not rely on `whereCan` to find actors whose only access is an owner-only grant
- do not protect per-organization routes with `role:...,any` or `@role('...', 'any')`; check the role in its scope
- do not expect an attribute ownership rule (`user_id`, `author_id`) to match non-user actors; use a closure
- do not document or use package internals (`AbilityResolver`, `AbilityCache`) from app code; the facade and trait are the public surface
