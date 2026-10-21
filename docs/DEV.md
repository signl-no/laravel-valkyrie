
## `Signl\Valkyrie\ValkyrieManager`

Singleton behind the `Signl\Valkyrie\Facades\Valkyrie` facade. Entity parameters accept a model instance, a model class string, `'*'`, or `null`.

| Method | Returns | Description |
|---|---|---|
| `ability(string $name, Model\|string\|null $entity = null, bool $onlyOwned = false, ?string $title = null)` | `Ability` | Find or create an ability; refreshes the cache on create. |
| `role(string $name, ?string $title = null, ?Model $scope = null)` | `Role` | Find or create a role definition; a scope makes it scope-only. |
| `allow(Model $actor, ?Model $on = null)` | `GrantBuilder` | Start an allow grant, optionally inside one scope. |
| `forbid(Model $actor, ?Model $on = null)` | `GrantBuilder` | Start a forbid grant. Forbids always beat allows. |
| `disallow(Model $actor, ?Model $on = null)` | `GrantBuilder` | Start removing allow rows. |
| `unforbid(Model $actor, ?Model $on = null)` | `GrantBuilder` | Start removing forbid rows. |
| `assign(string\|Role $role, Model\|array $actors, ?Model $on = null)` | `void` | Give actor(s) a role. Idempotent; creates a global role for an unknown name. |
| `retract(string\|Role $role, Model\|array $actors, ?Model $on = null)` | `void` | Take a role away. Unknown names are a no-op. |
| `can(Model $actor, string $ability, Model\|string\|null $entity = null, ?Model $on = null)` | `bool` | Explicit check. A `ScopedEntity`'s own scope wins over `$on`. |
| `cannot(...same...)` | `bool` | Negation of `can()`. |
| `check(...same...)` | `?bool` | Raw tri-state: `true`, `false`, or `null` when no rule applies. |
| `holdsRole(Model $actor, string $role, ?Model $scope = null)` | `bool` | No scope: holds the role anywhere. With one: globally or in that scope. |
| `ownedVia(string $model, string\|Closure $attribute)` | `void` | Register how ownership is decided for a model class. |
| `owns(Model $actor, Model $entity)` | `bool` | Ownership check (default attribute `user_id`). |
| `refresh()` | `void` | Invalidate every cached ability set. |
| `refreshFor(Model $actor)` | `void` | Invalidate one actor's cache (a role actor bumps everything). |

## `Signl\Valkyrie\Grants\GrantBuilder`

Returned by `allow` / `forbid` / `disallow` / `unforbid`. Each terminal call writes in one transaction, refreshes the cache, and dispatches events after commit. All return `Collection<int, Permission>` (empty on removals).

| Method | Description |
|---|---|
| `to(string\|array $abilities, Model\|string\|null $entity = null)` | One or more abilities: simple, on a model class, or on one instance. |
| `toManage(string $model, array $except = [])` | Every ability (`*`) on a model, minus `$except`. |
| `toOwn(string $model, ?array $only = null, array $except = [])` | Owner-only abilities on a model. `only` **or** `except`, never both (`InvalidArgumentException`). |
| `toOwnEverything(array $except = [])` | Owner-only access to every model. |
| `everything(array $except = [])` | The superuser grant: `*` on `*`. |

## `Signl\Valkyrie\Grants\GrantMode` (enum)

Cases: `Allow`, `Disallow`, `Forbid`, `Unforbid`.

| Method | Description |
|---|---|
| `creates(): bool` | `true` for `Allow` and `Forbid` (modes that create rows). |
| `forbidden(): bool` | `true` for `Forbid` and `Unforbid` (modes touching forbid rows). |

## `Signl\Valkyrie\Concerns\HasRolesAndAbilities` (trait)

Makes a model an actor; the Gate bridge engages for authenticated models using it.

| Method | Returns | Description |
|---|---|---|
| `abilities()` | `MorphToMany<Ability>` | Directly granted abilities; pivot: `forbidden`, `scope_type`, `scope_id`. |
| `roles()` | `MorphToMany<Role>` | Role assignments; pivot: `scope_type`, `scope_id`. |
| `allow(?Model $on = null)` | `GrantBuilder` | Shortcut for `Valkyrie::allow($this, $on)`. |
| `forbid(?Model $on = null)` | `GrantBuilder` | Shortcut for `Valkyrie::forbid($this, $on)`. |
| `assign(string\|Role $role, ?Model $on = null)` | `static` | Assign a role to this actor. Chainable. |
| `retract(string\|Role $role, ?Model $on = null)` | `static` | Retract a role. Chainable. |
| `getAbilities()` | `Collection<Ability>` | Directly allowed abilities. |
| `getForbiddenAbilities()` | `Collection<Ability>` | Directly forbidden abilities. |
| `isA(string $role, ?Model $scope = null)` | `bool` | No scope: holds the role anywhere. With one: globally or in that scope. |
| `isAn(string $role, ?Model $scope = null)` | `bool` | Alias of `isA()`. |
| `isNotA(string $role, ?Model $scope = null)` | `bool` | Negation of `isA()`. |
| `isAll(string ...$roles)` | `bool` | Holds every listed role. |
| `whereCan(string $ability, Model\|string\|null $entity = null, ?Model $scope = null)` | `Builder` (scope) | `User::whereCan(...)` — actors that can perform the ability and aren't forbidden. **Excludes owner-only grants.** |
| `whereIs(string $role, ?Model $scope = null)` | `Builder` (scope) | `User::whereIs(...)` — actors holding the role. |

## Middleware & Blade

| Feature | Usage |
|---|---|
| `role` middleware | `->middleware('role:admin,editor')` — any-of. Guests: `AuthenticationException` (401); denied: `AuthorizationException` (403). |
| `ability` middleware | `->middleware('ability:edit-posts,view-posts')` — any-of, resolved through the Gate bridge so forbids apply. |
| `@role` directive | `@role('admin') ... @else ... @endrole`, plus `@unlessrole('admin') ... @endrole`. Guests render the else branch. |
| Ability checks in Blade | Use the built-in `@can` — it flows through the Gate bridge. |

## `Signl\Valkyrie\Resolution\AbilityResolver`

| Method | Description |
|---|---|
| `check(Model $actor, string $ability, Model\|string\|null $entity = null, ?Model $scope = null): ?bool` | Resolves direct grants plus role grants. Forbid → `false`; allow → `true`; otherwise `null`. |

## `Signl\Valkyrie\Resolution\AbilityCache`

| Method | Description |
|---|---|
| `bump(): void` | Clear the request memo and increment the current partition's version (invalidates every actor). |
| `bumpActor(string $actorType, int\|string $actorKey): void` | Increment one actor's version (invalidates only that actor). |
| `flush(): void` | Clear the per-request memo only; store entries stay valid. |
| `enabled(): bool` | Whether the store layer is on (`valkyrie.cache.enabled`). |
| `rows(string $actorType, int\|string $actorKey, ?string $scopeType, int\|string\|null $scopeKey, Closure $load): array` | Cached ability rows for one actor and scope; `$load` fills misses. |

Store key format: `valkyrie:{partitionVersion}.{actorVersion}:{partition}:{actorType}:{actorKey}:{scopeType}:{scopeKey}`; version keys: `valkyrie:version:{partition}` and `valkyrie:version:{partition}:{actorType}:{actorKey}`. The partition comes from the bound `Contracts\CachePartition`. Version increments run after the surrounding database transaction commits.

### `Signl\Valkyrie\Resolution\DefaultCachePartition`

The default `CachePartition`: a constant `'default'`, correct whenever one application context owns the cache store.

## `Signl\Valkyrie\Ownership\OwnershipResolver`

| Member | Description |
|---|---|
| `owns(Model $actor, Model $entity): bool` | True when the registered attribute equals the actor's key, or the closure says so. Missing attribute → `false`, never an error. |
| `register(string $model, string\|Closure $attribute): void` | Set the ownership attribute or closure for a model class. Closures receive `($entity, $actor)`. |
| `DefaultAttribute` (const) | `'user_id'`. |

## Support classes

### `Signl\Valkyrie\Support\Models`

Resolves the model classes configured under `valkyrie.models.*`; a configured class must extend the package base model.

| Method | Description |
|---|---|
| `role(): class-string<Role>` (static) | The configured role model. |
| `ability(): class-string<Ability>` (static) | The configured ability model. |
| `permission(): class-string<Permission>` (static) | The configured permission model. |
| `assignedRole(): class-string<AssignedRole>` (static) | The configured assigned-role model. |

### `Signl\Valkyrie\Support\Morph`

| Member | Description |
|---|---|
| `type(Model\|string\|null $entity): ?string` (static) | Entity → stored morph type: instance → morph class, class-string → morph class, `'*'` → `'*'`, `null` → `null`. |
| `key(Model\|string\|null $entity): int\|string\|null` (static) | Instance → its key; everything else → `null`. |
| `Everything` (const) | `'*'`. |

### `Signl\Valkyrie\Support\ScopeConstraint`

| Method | Description |
|---|---|
| `apply(Builder $query, string $table, ?Model $scope): Builder` (static) | Restrict rows to global (`null`) or exactly the given scope. No parent inheritance. |
| `matches(?string $scopeType, int\|string\|null $scopeId, ?Model $scope): bool` (static) | Compare a stored scope with a model (null matches null). |

## Contracts

### `Signl\Valkyrie\Contracts\CachePartition`

Partitions ability cache keys; configure under `valkyrie.cache.partition`. Return a distinct value per tenant when the cache store is shared across tenants.

| Method | Description |
|---|---|
| `key(): string` | Non-null identifier embedded in every cache key and version. |

Shipped implementation: `Resolution\DefaultCachePartition` (default, single partition).

### `Signl\Valkyrie\Contracts\ScopedEntity`

| Method | Description |
|---|---|
| `accessScope(): ?Model` | The scope governing access to this model; wins over an explicit `$on`. |

## Models

All models read their table name from `valkyrie.tables.*`, are non-final (extend them and register the subclass under `valkyrie.models.*`), carry `#[Fillable]` and `#[UseFactory]` attributes (inherited by subclasses), and have factories in `Signl\Valkyrie\Database\Factories`.

| Model | Relations & members |
|---|---|
| `Ability` | `permissions(): HasMany<Permission>`. Attributes: `name`, `title`, `entity_type/id`, `only_owned`, `options` (array). |
| `Role` | `abilities(): MorphToMany<Ability>`, `assignments(): HasMany<AssignedRole>`, `scope(): MorphTo`, `isScoped(): bool`. Deleting a role also deletes its permission rows. |
| `AssignedRole` | `role(): BelongsTo<Role>`, `entity(): MorphTo`, `scope(): MorphTo`. No timestamps. |
| `Permission` | `ability(): BelongsTo<Ability>`, `entity(): MorphTo`, `scope(): MorphTo`. `forbidden` (bool), `except` (array\|null). No timestamps. |

## Events

All in `Signl\Valkyrie\Events`, dispatched after commit, with public readonly constructor properties. They are the integration seam for audit/logging packages.

| Event | Constructor |
|---|---|
| `AbilityGranted` | `(Model $actor, Ability $ability, ?Model $scope = null)` |
| `AbilityForbidden` | `(Model $actor, Ability $ability, ?Model $scope = null)` |
| `AbilityRevoked` | `(Model $actor, Ability $ability, ?Model $scope = null, bool $wasForbidden = false)` |
| `RoleAssigned` | `(Model $actor, Role $role, ?Model $scope = null)` |
| `RoleRetracted` | `(Model $actor, Role $role, ?Model $scope = null)` |

## Exceptions

Both extend `InvalidArgumentException`:

| Exception | Factory methods |
|---|---|
| `AmbiguousRoleException` | `forName(string $name)` — several scoped definitions, no scope given. |
| `ScopeMismatchException` | `forName(string $name, Model $requested)`, `forRole(Role $role, ?Model $requested)` — role defined for a different scope. |

## Console commands

| Command | Description |
|---|---|
| `valkyrie:grant-superuser {email}` | Grant the configured superuser role (`valkyrie.superuser.role`, default `system-admin`) with `everything()` to the user. Idempotent lockout guard. |
