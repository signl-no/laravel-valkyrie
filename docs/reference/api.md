# API reference

For a one-page summary with examples, see the [cheat sheet](../../CHEATSHEET.md).

**Entity arguments.** Wherever a parameter is named `$entity`, it accepts a model instance (one record), a model class string (every record of that model), `'*'` (every model, write methods only) or `null` (no entity).

**Scope arguments.** `$on` and `$scope` accept an Eloquent model or `null`. `null` means global.

---

## `Signl\Valkyrie\Facades\Valkyrie`

This facade resolves to the `ValkyrieManager` singleton, and Laravel registers the `Valkyrie` alias automatically.

### Writing

| Method | Returns | Description |
|---|---|---|
| `allow(Model $actor, ?Model $on = null)` | `GrantBuilder` | Starts an allow grant for an actor or a `Role`. |
| `forbid(Model $actor, ?Model $on = null)` | `GrantBuilder` | Starts a forbid. A matching forbid always beats every allow. |
| `disallow(Model $actor, ?Model $on = null)` | `GrantBuilder` | Starts removing allow rows. |
| `unforbid(Model $actor, ?Model $on = null)` | `GrantBuilder` | Starts removing forbid rows. |
| `assign(string\|Role $role, Model\|array $actors, ?Model $on = null)` | `void` | Gives one or more actors a role. Idempotent. Creates a global role for an unknown name. |
| `retract(string\|Role $role, Model\|array $actors, ?Model $on = null)` | `void` | Takes a role away. Does nothing for an unknown name. |
| `role(string $name, ?string $title = null, ?Model $scope = null)` | `Role` | Finds or creates a role definition. A scope makes it a definition for that scope only. |
| `ability(string $name, Model\|string\|null $entity = null, bool $onlyOwned = false, ?string $title = null)` | `Ability` | Finds or creates an ability definition. |
| `ownedVia(string $model, string\|Closure $attribute)` | `void` | Registers the ownership rule for a model class and its subclasses. An attribute rule only matches actors of the auth user model (`auth.providers.users.model`). A closure receives `($entity, $actor)` and works for any actor type. |
| `refresh()` | `void` | Invalidates every cached ability set in the current cache partition. |
| `refreshFor(Model $actor)` | `void` | Invalidates one actor's cache. Passing a `Role` invalidates everything. |

### Reading

| Method | Returns | Description |
|---|---|---|
| `can(Model $actor, string $ability, Model\|string\|null $entity = null, ?Model $on = null)` | `bool` | `true` only when `check()` returns `true`. |
| `cannot(...)` | `bool` | The negation of `can()`. |
| `check(...)` | `?bool` | The raw result: `false` when a forbid matches, `true` when an allow matches, `null` when nothing matches. |
| `holdsRole(Model $actor, string $role, ?Model $scope = null)` | `bool` | Without a scope: holds the role in any scope. With one: holds it globally or in that scope. |
| `holdsGlobalRole(Model $actor, string $role)` | `bool` | Holds the role through a global assignment. Scoped assignments don't count. |
| `owns(Model $actor, Model $entity)` | `bool` | Applies the registered ownership rule. The default rule compares `user_id` with the actor's key, for actors of the auth user model only. |

When the entity implements `ScopedEntity`, its `accessScope()` replaces `$on`. A `null` access scope means global.

---

## `Signl\Valkyrie\Grants\GrantBuilder`

Returned by `allow`, `forbid`, `disallow` and `unforbid`. Each terminal method writes in one database transaction, invalidates the actor's cache, and returns `Collection<int, Permission>`. Removals return an empty collection.

| Method | Ability written |
|---|---|
| `to(string\|array $abilities, Model\|string\|null $entity = null)` | Each named ability on `$entity`. |
| `toManage(string $model, array $except = [])` | `*` on `$model`, except the names in `$except`. |
| `toOwn(string $model, ?array $only = null, array $except = [])` | Owner-only. Either each name in `$only`, or `*` on `$model` except the names in `$except`. Passing both throws `InvalidArgumentException`. |
| `toOwnEverything(array $except = [])` | Owner-only `*` on `*`, except the names in `$except`. |
| `everything(array $except = [])` | `*` on `*`, except the names in `$except`. This is the superuser grant. |

**Idempotency.** Repeating an identical grant writes nothing and dispatches no event. Repeating a wildcard grant with a different `except` list updates the stored list and dispatches the grant event again.

**Removals never create abilities.** `disallow()` and `unforbid()` only look up existing ability definitions. Removing an ability that was never defined does nothing.

**`except` is not a forbid.** It removes names from the wildcard's match. A name listed in `except` returns `null` (no opinion), not `false`.

---

## `Signl\Valkyrie\Grants\GrantMode`

A unit enum with the cases `Allow`, `Disallow`, `Forbid` and `Unforbid`.

| Method | Returns `true` for |
|---|---|
| `creates(): bool` | `Allow`, `Forbid` |
| `forbidden(): bool` | `Forbid`, `Unforbid` |

---

## `Signl\Valkyrie\Concerns\HasRolesAndAbilities`

Add this trait to any model that should act as an actor. The Gate bridge only answers for authenticated users whose class uses it.

Deleting an actor removes its permission rows and role assignments and invalidates its cache. For models that use `SoftDeletes`, this happens on `forceDelete()` only. No Valkyrie events are dispatched for this cleanup.

### Relations

| Method | Returns | Pivot columns |
|---|---|---|
| `abilities()` | `MorphToMany<Ability>` | `forbidden`, `scope_type`, `scope_id` |
| `roles()` | `MorphToMany<Role>` | `scope_type`, `scope_id` |

### Writing

| Method | Returns | Equivalent |
|---|---|---|
| `allow(?Model $on = null)` | `GrantBuilder` | `Valkyrie::allow($this, $on)` |
| `forbid(?Model $on = null)` | `GrantBuilder` | `Valkyrie::forbid($this, $on)` |
| `assign(string\|Role $role, ?Model $on = null)` | `static` | `Valkyrie::assign($role, $this, $on)` |
| `retract(string\|Role $role, ?Model $on = null)` | `static` | `Valkyrie::retract($role, $this, $on)` |

There are no trait shortcuts for `disallow` and `unforbid`. Use the facade.

### Reading

| Method | Returns | Description |
|---|---|---|
| `getAbilities()` | `Collection<Ability>` | Abilities allowed directly, in any scope. Abilities from roles aren't included. |
| `getForbiddenAbilities()` | `Collection<Ability>` | Abilities forbidden directly, in any scope. |
| `isA(string $role, ?Model $scope = null)` | `bool` | Same as `Valkyrie::holdsRole($this, ...)`. |
| `isAn(...)` | `bool` | An alias of `isA()`. |
| `isNotA(...)` | `bool` | The negation of `isA()`. |
| `isAll(string ...$roles)` | `bool` | Holds every listed role, in any scope. |

### Query scopes

| Scope | Description |
|---|---|
| `whereCan(string $ability, Model\|string\|null $entity = null, ?Model $scope = null)` | Actors with a matching allow (direct or through a role) and no matching forbid. Owner-only grants are left out. A `ScopedEntity`'s access scope replaces `$scope`. |
| `whereIs(string $role, ?Model $scope = null)` | Actors holding the role: in any scope without `$scope`, or globally or in `$scope` with it. |

---

## `Signl\Valkyrie\Concerns\IsAccessScope`

Add this trait to models used as scopes. Deleting the model removes the permission rows, role assignments and role definitions scoped to it, then invalidates the whole cache. For models that use `SoftDeletes`, this happens on `forceDelete()` only. No Valkyrie events are dispatched for this cleanup.

---

## `Signl\Valkyrie\Contracts\ScopedEntity`

| Method | Description |
|---|---|
| `accessScope(): ?Model` | The scope that governs access to this record. It always replaces any explicit `$on`, and `null` means global. |

## `Signl\Valkyrie\Contracts\CachePartition`

| Method | Description |
|---|---|
| `key(): string` | Part of every cache key and version key. Return a different value for each tenant when tenants share a cache store. |

The shipped implementation is `Signl\Valkyrie\Resolution\DefaultCachePartition`, which always returns `'default'`.

---

## Models

All four models read their table name from `valkyrie.tables.*`, aren't `final`, and use the `#[Fillable]` and `#[UseFactory]` attributes. Their factories live in `Signl\Valkyrie\Database\Factories`.

### `Signl\Valkyrie\Models\Ability`

| Member | Description |
|---|---|
| `name` | `string`. A free-form ability name, or `*`. |
| `title` | `?string` |
| `entity_type`, `entity_id` | The morph target. `null` means no entity, and `entity_type` `*` means every model. |
| `only_owned` | `bool`. When `true`, the ability only matches records the actor owns. |
| `options` | `?array`. Free-form data; Valkyrie doesn't read it. |
| `permissions()` | `HasMany<Permission>` |

### `Signl\Valkyrie\Models\Role`

| Member | Description |
|---|---|
| `name`, `title` | `string`, `?string` |
| `scope_type`, `scope_id` | Set for a scoped definition. |
| `abilities()` | `MorphToMany<Ability>`, with the pivot columns `forbidden`, `scope_type` and `scope_id`. |
| `assignments()` | `HasMany<AssignedRole>` |
| `scope()` | `MorphTo` |
| `isScoped(): bool` | `true` when `scope_type` is set. |

Deleting a role deletes its assignments (through a database cascade) and its permission rows (through a model event).

### `Signl\Valkyrie\Models\AssignedRole`

| Member | Description |
|---|---|
| `role_id`, `entity_type`, `entity_id`, `scope_type`, `scope_id` | One role held by one actor, optionally in one scope. |
| `role()` | `BelongsTo<Role>` |
| `entity()`, `scope()` | `MorphTo` |

This model has no timestamps.

### `Signl\Valkyrie\Models\Permission`

| Member | Description |
|---|---|
| `ability_id`, `entity_type`, `entity_id` | Links one ability to one actor or role. |
| `forbidden` | `bool` |
| `except` | `?array`. Names removed from a wildcard ability. |
| `scope_type`, `scope_id` | The optional scope. |
| `ability()` | `BelongsTo<Ability>` |
| `entity()`, `scope()` | `MorphTo` |

This model has no timestamps.

---

## Internal classes

These are public but not part of the stable surface. They're documented for people extending or debugging the package.

| Class | Purpose |
|---|---|
| `Resolution\AbilityResolver` | `check(Model $actor, string $ability, Model\|string\|null $entity, ?Model $scope): ?bool` |
| `Resolution\AbilityCache` | `bump()`, `bumpActor(string $type, int\|string $key)`, `flush()`, `rolledBack(int $remainingLevel)`, `enabled()`, `rows(...)`. See [Caching](../explanation/caching.md). |
| `Ownership\OwnershipResolver` | `owns()`, `register()`. The constant `DefaultAttribute` is `'user_id'`. Attribute rules only match actors of the auth user model. |
| `Assignments\RoleAssignment` | Decides which role definition a name refers to, and writes assignments. |
| `Support\Models` | Static `role()`, `ability()`, `permission()` and `assignedRole()` return the configured classes. |
| `Support\Morph` | Static `type()` and `key()` turn entity arguments into morph values. The constant `Everything` is `'*'`. |
| `Support\ScopeConstraint` | Static `apply()` limits a query to global rows or one exact scope. Static `matches()` compares a stored scope with a model. Static `effective()` returns the scope a check runs in. |
