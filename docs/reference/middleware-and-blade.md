# Middleware and Blade

## Route middleware

Both aliases are registered when the router resolves.

| Alias | Class | Parameters | Passes when |
|---|---|---|---|
| `role` | `Signl\Valkyrie\Http\Middleware\EnsureHasRole` | One or more role names, optionally followed by `any` | The user holds **any** listed role through a global assignment. With a trailing `any`, an assignment in any scope also counts. |
| `ability` | `Signl\Valkyrie\Http\Middleware\EnsureHasAbility` | One or more ability names | `$user->can($ability)` is true for **any** listed ability. |

```php
->middleware('role:admin,editor')
->middleware('role:admin,editor,any')
->middleware('ability:export-reports,view-reports')
```

### Failures

| Condition | Exception | Default response |
|---|---|---|
| No authenticated user | `Illuminate\Auth\AuthenticationException` | Redirect to login, or 401 |
| Authenticated, every check fails | `Illuminate\Auth\Access\AuthorizationException` | 403 |

### Scope behavior

- `role` uses `Valkyrie::holdsGlobalRole($user, $role)`, so only global assignments satisfy it. With a trailing `any`, it uses `Valkyrie::holdsRole($user, $role)`, and an assignment in any scope satisfies it.
- Because `any` is read as a flag when it's the last parameter, a role named `any` can't be the last role listed.
- `ability` calls the Gate without an entity, so only global grants match. Because it goes through the Gate, forbids, `Gate::define()` callbacks and policies all apply.

## Blade directives

| Directive | Description |
|---|---|
| `@role('name') … @endrole` | Renders when the signed-in user holds the role through a global assignment. |
| `@role('name', $scope) … @endrole` | Renders when the user holds the role globally or in `$scope`. |
| `@role('name', 'any') … @endrole` | Renders when the user holds the role in any scope, or globally. |
| `@elserole('name')` | An else-if branch inside `@role`. Takes the same arguments. |
| `@else` | The else branch. |
| `@unlessrole('name') … @endrole` | Renders when `@role` with the same arguments wouldn't. |

Guests never pass `@role`. For abilities, use Laravel's `@can`, `@cannot` and `@canany`, which go through the Gate bridge.
