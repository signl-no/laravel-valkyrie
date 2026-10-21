# Caching

Authorization checks run constantly, often several times per request and per Blade view. This page explains how Valkyrie keeps them fast without serving stale rules.

## What gets cached

Valkyrie doesn't cache answers to individual checks. It caches the **rows**: every permission an actor can reach in one scope, either directly or through roles held there. Matching names, entities and ownership against those rows happens in PHP for each check. One cache entry therefore answers every ability check for that actor in that scope, and the cache never needs to know which abilities you ask about.

## Two layers

1. **The per-request memo.** This is an in-memory array on a singleton, and it's always on. Repeated checks in one request cost nothing after the first. Valkyrie clears it at every queue job and, under Octane, at every request, so long-lived workers don't carry rules over.
2. **The versioned store.** This is a Laravel cache store (`valkyrie.cache.store`), shared across processes. It's on by default and can be turned off with `VALKYRIE_CACHE=false`.

## Why versions instead of deleting keys

The obvious way to invalidate a cache is to delete the affected keys. That breaks down quickly here. A grant to a role affects every holder in every scope, and those keys can't be listed without scanning the store, which many stores can't do.

Valkyrie embeds version numbers in each key instead:

```
valkyrie:{partitionVersion}.{actorVersion}:{namespace}:{actorType}:{actorKey}:{scopeType}:{scopeKey}
```

To invalidate, Valkyrie increments a version. Every key built from the old version becomes unreachable at once, and the TTL (`valkyrie.cache.ttl`) eventually removes the orphaned entries. Invalidation costs one `increment`, however many entries it affects.

A missing version key starts at a random number, not at zero. If the cache store evicts a version key under memory pressure, the new version can't land on a number that older entries were cached under. Without this, an eviction could bring back rules that had already been revoked.

There are two kinds of version, which keep invalidation as narrow as the write:

| Write | Bumps | Effect |
|---|---|---|
| A grant, forbid or assignment for a user | That actor's version | Only that actor's entries |
| A grant or forbid for a role | The partition version | Every entry, because the role may be held by anyone |
| A role or ability definition created, deleted, renamed, re-targeted or re-scoped | The partition version | Every entry |
| A role or ability edit that can't change resolution (such as a title) | Nothing | — |

One consequence: when a grant uses an ability name, entity and owner-only combination that doesn't exist yet, Valkyrie first creates the ability definition, and that bumps the partition version. Once the catalog of abilities is stable, user grants stay narrow.

## Why bumps wait for the commit

If Valkyrie bumped the version inside a transaction, another process could read the old rows from the database, before the commit, and cache them under the new version. That stale entry would then live until the next bump.

So inside a transaction, Valkyrie clears its own memo immediately and defers the store increment until after the commit. A rolled-back grant never bumps at all. While a bump is pending, the writing process skips the store and reads straight from the database, so it sees its own uncommitted grants.

Valkyrie tracks transactions on the connection its models use, so a transaction on an unrelated connection never decides when a bump runs. When a transaction on that connection rolls back, Valkyrie clears the memo again, so rows read from the discarded transaction don't outlive it. If an outer transaction is still open after a nested rollback, the pending bump stays in place until that outer transaction finishes.

## The safety net

The facade and the trait invalidate as part of every write. Rows also get written in other ways: admin screens editing models directly, seeders, factories, tinker. To cover those, Valkyrie listens to the `created`, `updated` and `deleted` events on all four models and applies the same invalidation. The safety net doesn't cover mass updates and deletes that skip model events (`Permission::query()->delete()`). After one of those, call `Valkyrie::refresh()`.

## Namespaces and partitions

Every key, in both the memo and the store, starts with a namespace built from three parts:

- the **partition** key from the configured `CachePartition` (the default returns `default`);
- the **name of the connection** Valkyrie's models use (the default connection, unless your model subclasses set `$connection`);
- the **database name** on that connection.

Each part, and the actor and scope parts that follow it, is URL-encoded, so no value can contain a delimiter and pass for a different key.

The connection and database parts keep database-per-tenant setups isolated without any configuration, even inside one process that switches tenants. The partition covers what they can't: tenants that share one database. [Multi-tenancy design](multi-tenancy-design.md) covers this in more detail.

## The trade-off in long-lived processes

The memo also stores the version numbers it has read, so each check doesn't cost a cache round trip. As a result, a process sees another process's rule changes at its next request or job, not in the middle of one. For typical web and queue workloads, where a request or job lasts milliseconds to seconds, this doesn't matter. A single long-running job that needs to see changes immediately can call `Valkyrie::refreshFor($actor)` or rely on its own writes, which always clear the memo.
