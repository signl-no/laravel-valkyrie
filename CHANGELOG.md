# Release Notes

## [Unreleased](https://github.com/signlphp/laravel-valkyrie/compare/v0.9.0...1.x)

## v0.9.0 - Unreleased

Initial release: the Valkyrie RBAC system extracted into a standalone package.

### Added

- `Valkyrie` facade and `ValkyrieManager` with the full grant API: `ability`, `role`, `allow`/`forbid`/`disallow`/`unforbid` grant builders, `assign`/`retract`, `can`/`cannot`/`check`, `holdsRole`, `ownedVia`/`owns`, `refresh`/`refreshFor`.
- `HasRolesAndAbilities` actor trait with relations, role checks, and `whereCan`/`whereIs` query scopes.
- Tri-state resolution bridged into the Gate (`Gate::before`): forbid → deny, allow → grant, no rule → fall through to policies.
- Scoped grants and role definitions with exact-match scope semantics, `ScopedEntity` contract, and ownership rules (`only_owned`, `toOwn`).
- Versioned ability cache with per-actor invalidation, per-request memoization, commit-safe version bumps, and automatic memo flushing for queue workers and Octane.
- `CachePartition` contract (`valkyrie.cache.partition`) embedding a partition in every cache key, so multi-tenant apps sharing one cache store keep cached ability sets isolated per tenant.
- Extensible models: subclass `Role`/`Ability`/`Permission`/`AssignedRole` and register under `valkyrie.models.*`; Laravel 13 class attributes (`#[Fillable]`, `#[UseFactory]`) are inherited.
- `role` and `ability` route middleware and the `@role`/`@unlessrole` Blade directives.
- Domain events (`AbilityGranted`, `AbilityForbidden`, `AbilityRevoked`, `RoleAssigned`, `RoleRetracted`), dispatched after commit.
- `valkyrie:grant-superuser` command with config-driven role name.
- Migrations load from the package by default; publish via `laravel-valkyrie-migrations` (filename preserved) and set `valkyrie.migrations => false` to take over the schema.
- Model factories published for consuming apps.
