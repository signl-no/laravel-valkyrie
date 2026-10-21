# Scope grants to a model

Use this guide to limit an ability or a role to one model instance, such as an office, a team or a project. A user can then be a manager in one office without being a manager everywhere.

**Before you start:** your actor model uses `HasRolesAndAbilities`, and the scope model is an Eloquent model.

## Grant an ability inside one scope

Pass the scope as the second argument to `Valkyrie::allow()`, or as the only argument to the trait's `allow()`:

```php
use Signl\Valkyrie\Facades\Valkyrie;

Valkyrie::allow($user, $office)->to('approve');
$user->allow($office)->to('approve', Report::class);
```

`forbid()`, `disallow()` and `unforbid()` take the scope the same way. To remove a scoped grant, pass the same scope you granted it with.

## Assign a role inside one scope

```php
$user->assign('manager', $office);
Valkyrie::assign('manager', [$alice, $bob], $office);

$user->retract('manager', $office);
```

The role definition stays global, and only the assignment is scoped. Abilities granted to the role apply to this user only when a check runs in `$office`.

## Check inside a scope

Pass the scope with the `on:` argument:

```php
Valkyrie::can($user, 'approve', on: $office);
Valkyrie::can($user, 'approve', Report::class, on: $office);
```

Laravel's own surfaces (`$user->can()`, `@can`, `authorize()`, policies) don't pass a scope. To make them scope-aware, use the next section.

## Let a model carry its own scope

Implement `ScopedEntity` on any model that belongs to a scope:

```php
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Contracts\ScopedEntity;

class Report extends Model implements ScopedEntity
{
    public function accessScope(): ?Model
    {
        return $this->office;
    }
}
```

When a check targets a `Report` instance, Valkyrie checks inside the scope that `accessScope()` returns:

```php
$user->can('approve', $report);   // checked inside $report->office
```

The entity decides its own scope, and an explicit `on:` argument is ignored. If `accessScope()` returns `null`, the check runs globally, even when you pass `on:`. That way a record with no scope can't be opened through a grant in whatever scope the caller passes.

Only instances carry a scope. A class-level check such as `$user->can('approve', Report::class)` still runs globally.

## Define a role that only exists in one scope

When each office needs its own role with different abilities, create a scoped role definition:

```php
$osloAdmin = Valkyrie::role('office-admin', 'Office admin', scope: $oslo);
Valkyrie::allow($osloAdmin)->toManage(Report::class);

$user->assign('office-admin', $oslo);   // the Oslo definition
$user->assign($osloAdmin);              // also Oslo: the definition forces its scope
```

Assigning a scoped definition in a different scope throws `ScopeMismatchException`. When several scoped definitions share a name and there's no global one, assigning by name without a scope throws `AmbiguousRoleException`. Pass the scope, or pass the `Role` model itself.

## Verify

```php
Valkyrie::can($user, 'approve', on: $office);        // true
Valkyrie::can($user, 'approve', on: $otherOffice);   // false
Valkyrie::can($user, 'approve');                      // false: a global check ignores scoped grants
$user->isA('manager', $office);                       // true
```

## Watch out for

- **Scopes match exactly.** A grant in a parent office never applies to a child office. To cover both, grant in both scopes.
- **Global grants apply in every scope.** A user with a global `approve` grant passes `Valkyrie::can($user, 'approve', on: $anyOffice)`.
- **`role:` middleware only counts global assignments by default.** A role assigned only inside a scope fails `role:manager`, and `role:manager,any` accepts it. See [Protect routes and views](protect-routes-and-views.md).

## Clean up when a scope is deleted

Add the `IsAccessScope` trait to every model you use as a scope:

```php
use Signl\Valkyrie\Concerns\IsAccessScope;

class Office extends Model
{
    use IsAccessScope;
}
```

Deleting the office then removes the grants, role assignments and role definitions scoped to it. Without the trait, those rows stay behind, and on a database that reuses IDs, a new office could inherit them. For soft-deleting models, the cleanup runs on `forceDelete()` only.

## Related

- [Rule resolution](../explanation/rule-resolution.md) explains why scope matching is exact.
- [Exceptions](../reference/exceptions.md) lists the exceptions and what throws them.
