# Protect routes and views

Use this guide to restrict routes and Blade output by role or ability.

**Before you start:** your user model uses `HasRolesAndAbilities`. Valkyrie registers the `role` and `ability` middleware aliases and the `@role` directive for you.

## Protect a route by ability

For checks against a model bound to the route, use Laravel's `can` middleware. The check goes through Valkyrie's Gate bridge, and if the model implements `ScopedEntity`, its scope is applied:

```php
Route::put('/reports/{report}', [ReportController::class, 'update'])
    ->can('update', 'report');
```

For abilities that aren't tied to a model, use the `ability` middleware. It passes if the user has **any** of the listed abilities:

```php
Route::get('/exports', ExportController::class)
    ->middleware('ability:export-reports,view-reports');
```

## Protect a route by role

The `role` middleware passes if the user holds **any** of the listed roles through a **global** assignment:

```php
Route::prefix('admin')
    ->middleware('role:system-admin,support')
    ->group(base_path('routes/admin.php'));
```

To also accept assignments made inside a scope, add `any` as the last parameter:

```php
Route::get('/dashboard', DashboardController::class)
    ->middleware('role:manager,any');
```

## Choose a middleware when you use scopes

The two middleware handle scopes differently:

| Middleware | Passes when |
|---|---|
| `role:admin` | The user holds `admin` through a **global** assignment. |
| `role:admin,any` | The user holds `admin` in **any** scope, or globally. |
| `ability:x` | The user has a **global** grant for `x`. Scoped grants never match. |

If you assign roles per organization, `role:admin,any` lets an Oslo admin through on a Bergen route. Check the scope inside the controller instead:

```php
public function show(Office $office)
{
    abort_unless(request()->user()->isA('admin', $office), 403);

    // ...
}
```

You can also bind a `ScopedEntity` model to the route and use `->can('ability', 'model')`.

## Show or hide Blade content

Abilities use Laravel's built-in `@can`:

```blade
@can('update', $report)
    <a href="{{ route('reports.edit', $report) }}">Edit</a>
@endcan
```

Roles use `@role`, which also accepts `@else`:

```blade
@role('manager')
    <x-manager-dashboard />
@else
    <x-standard-dashboard />
@endrole

@unlessrole('manager')
    <p>Ask a manager to approve this.</p>
@endrole
```

Like the `role` middleware, `@role` counts only **global** assignments by default. Pass a scope to check inside it, or `'any'` to accept an assignment in any scope:

```blade
@role('manager', $office)
    {{-- holds manager globally or in $office --}}
@endrole

@role('manager', 'any')
    {{-- holds manager in any scope --}}
@endrole
```

`@role` is always false for guests.

## Verify

- As a guest, a protected route throws `AuthenticationException`. By default, that redirects to the login page or returns 401.
- As a user without access, the route throws `AuthorizationException`, which returns 403 through your exception handler.

## Related

- [Middleware and Blade reference](../reference/middleware-and-blade.md)
- [Scope grants to a model](scope-grants-to-a-model.md)
