# Find actors by ability or role

Use this guide to query the actors who can perform an ability or who hold a role. Typical uses are listing a project's approvers or notifying every manager.

**Before you start:** your actor model uses `HasRolesAndAbilities`, which adds the `whereCan` and `whereIs` query scopes.

## Find actors who can perform an ability

```php
User::whereCan('export-reports')->get();
User::whereCan('view', Report::class)->get();
User::whereCan('update', $report)->get();
User::whereCan('approve', Report::class, $office)->get();   // inside one scope
```

The results include actors who have the ability directly or through a role. Actors with a matching forbid, direct or through a role, are left out.

When the entity implements `ScopedEntity`, its access scope is used, exactly as in `$user->can()`. A forbid in the record's office therefore removes the actor from the results.

## Find actors who hold a role

```php
User::whereIs('manager')->get();            // holds it in any scope
User::whereIs('manager', $office)->get();   // holds it globally or in $office
```

## Combine with other constraints

Both are regular query scopes, so they chain like any other:

```php
User::query()
    ->whereIs('manager', $office)
    ->whereCan('approve', Report::class, $office)
    ->whereNotNull('email_verified_at')
    ->get();
```

## Watch out for

- **Owner-only grants are skipped.** Whether a record is owned depends on the record, and this query is about actors, so it can't tell. An actor whose only route to an ability is a `toOwn()` grant won't appear in the results.
- **`whereCan` is an approximation.** It covers direct grants, role grants, wildcards, `except` lists, forbids and scopes. To know exactly what one actor can do, use `$user->can()`.

## Related

- [Known limitations](../explanation/known-limitations.md)
