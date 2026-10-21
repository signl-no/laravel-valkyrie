# Exceptions

Valkyrie's exceptions live in `Signl\Valkyrie\Exceptions` and extend `InvalidArgumentException`.

## `ScopeMismatchException`

Thrown by `assign()` and `retract()`.

| Factory | Thrown when |
|---|---|
| `forRole(Role $role, ?Model $requested)` | A scoped role definition is assigned or retracted in a different scope. |
| `forName(string $name, Model $requested)` | A name is given with a scope, no definition exists for that scope or globally, and at least one definition exists for another scope. |

## `AmbiguousRoleException`

Thrown by `assign()` and `retract()`.

| Factory | Thrown when |
|---|---|
| `forName(string $name)` | A name is given without a scope, has no global definition, and has more than one scoped definition. |

To avoid it, pass a scope or the `Role` model itself.

## How a role name is resolved

When `assign()` or `retract()` receives a name rather than a `Role` model:

| Scope given? | Lookup order |
|---|---|
| Yes | 1. The definition for that scope. 2. The global definition. 3. If other scoped definitions exist, throw `ScopeMismatchException`. 4. Otherwise `assign()` creates a global definition, and `retract()` does nothing. |
| No | 1. The global definition. 2. The only scoped definition, if there's exactly one. 3. If there's more than one, throw `AmbiguousRoleException`. 4. Otherwise `assign()` creates a global definition, and `retract()` does nothing. |

When the resolved definition is scoped, its scope is used for the assignment.

## Other exceptions

| Exception | Thrown when |
|---|---|
| `InvalidArgumentException` | `toOwn()` receives both `only` and `except`. |
| `InvalidArgumentException` | A `valkyrie.models.*` class doesn't extend its base model. |
| `Illuminate\Auth\AuthenticationException` | The `role` or `ability` middleware runs without an authenticated user. |
| `Illuminate\Auth\Access\AuthorizationException` | The `role` or `ability` middleware denies an authenticated user. |
