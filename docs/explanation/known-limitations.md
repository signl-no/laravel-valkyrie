# Known limitations

Each limitation below is a deliberate trade-off. This page explains what you give up and why.

## `whereCan()` leaves out owner-only grants

`User::whereCan('update', $post)` returns actors with a matching allow and no matching forbid, but it skips owner-only grants.

**Why:** ownership is decided at check time by a rule you register, which may be a PHP closure. A database query over actors can't run a closure for every row. Even an attribute rule would need a join for each model type. Rather than return partial or misleading results, `whereCan()` leaves owner-only grants out entirely.

**What to do:** to find who can act on a particular record through ownership, query the owner column directly, for example `$post->author`. Combine that with `whereCan()` when you need both.

## Changes from another process appear at the next request or job

A long-running process doesn't see a rule change made by another process until its next request or queue job.

**Why:** the per-request memo stores the cache version numbers it has read. Without that, every check would cost a round trip to the cache store just to confirm nothing had changed. Clearing the memo at request and job boundaries keeps checks cheap while bounding staleness to one unit of work. See [Caching](caching.md).

**What to do:** in a job that must see changes from other processes mid-run, call `Valkyrie::refreshFor($actor)` before the check.

## Gate checks don't take a scope

`$user->can('approve')` always runs globally, because Laravel's Gate has no scope parameter.

**Why:** Valkyrie answers through `Gate::before`, which receives only the ability name and arguments. Adding a scope convention to Gate arguments would collide with policies that use those arguments.

**What to do:** implement `ScopedEntity` on models that belong to a scope, so the entity carries its scope into the check. Use `Valkyrie::can(..., on: $scope)` for checks with no entity.

## Scopes don't inherit

A grant in a parent scope doesn't apply in child scopes.

**Why:** Valkyrie can't know how your scope models relate, and inheritance that guesses wrong grants access nobody intended. See [Rule resolution](rule-resolution.md#how-scopes-match).

## Mass queries skip invalidation

`Permission::query()->where(...)->delete()` and similar mass operations don't fire model events, so the cache isn't invalidated.

**Why:** this is how Eloquent works. Mass operations skip model hydration and events by design.

**What to do:** call `Valkyrie::refresh()` after mass writes to Valkyrie's tables.
