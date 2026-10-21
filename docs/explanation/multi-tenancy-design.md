# Multi-tenancy design

Valkyrie is designed to work with multi-tenancy without dictating how you do it. This page explains that choice and what it means for your setup. For step-by-step instructions, see [Set up multi-tenancy](../how-to/set-up-multi-tenancy.md).

## Why there's no tenancy layer

Laravel apps handle tenancy in very different ways: a database per tenant, shared tables with a `tenant_id`, a mix of both, or no tenancy package at all, with organizations modeled as ordinary records. Each tenancy package has its own conventions for connections, global scopes and cache separation.

If Valkyrie added its own `tenant_id` handling, it would duplicate what your tenancy package already does, and it would probably conflict with it. So Valkyrie leaves row scoping to whatever your app already uses and stays out of the way.

## What Valkyrie provides instead

There are three things a tenancy package can't do for Valkyrie, so Valkyrie provides them.

**Models that can be swapped.** All four models are resolved from config. Your tenancy package's model trait (a global scope plus automatic `tenant_id` filling) can be added to Valkyrie's tables by subclassing, without forking the package.

**A migration you can take over.** Publishing the migration and turning off the package copy lets you add tenant columns and change unique indexes. Each tenant then gets its own catalog of roles and abilities, so two tenants can each have a role called `manager`.

**Cache isolation.** This is the one piece no tenancy package can reach. Valkyrie's cache keys are built from the actor's morph type and key, and actor `1` exists in every tenant.

- In a database-per-tenant setup, Valkyrie adds the active connection and database name to every key, so tenants stay apart without configuration.
- When tenants share one database, those parts are identical for every tenant. Implementing `CachePartition` adds the tenant to every key and closes that gap with one method.

## Scopes are not tenancy

Scopes and tenancy can look similar, since both limit access to "an organization". They work at different levels:

- **Tenancy** decides which rows exist. Another tenant's data is invisible.
- **Scopes** decide which rules apply. Every scope's data is visible to the database, and Valkyrie limits who may act on it.

If your "tenants" share one database and users can belong to several of them, scopes are often all you need. If tenants must never see each other's rows, use a tenancy package and let Valkyrie's models follow it.

## Hybrid setups

Some apps keep smaller tenants in shared tables and give large ones a dedicated database. The two mechanisms compose:

- The extended models scope rows in the shared database. In a dedicated database, every row belongs to the tenant, so the scope always matches and does no harm.
- Valkyrie uses the default connection, so the tenancy package's connection switching sends queries to the right database.
- In the shared database, a cache partition keyed by tenant separates the tenants. Each dedicated database is kept apart by its database name.
