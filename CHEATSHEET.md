# Valkyrie cheat sheet

How access is set up, how it is checked, and which rule wins. For concepts and recipes see the **[README](README.md)**.

```php
use Signl\Valkyrie\Facades\Valkyrie;
```

## 1. Set up access

### Grant abilities

```php
$user->allow()->to('export-reports');                      // simple ability, no entity
$user->allow()->to(['view', 'update'], Report::class);     // on every Report
$user->allow()->to('update', $report);                     // on one instance only
$user->allow()->toManage(Report::class, except: ['delete']); // every Report ability minus some
$user->allow()->toOwn(Report::class, only: ['update']);    // only on instances the user owns
$user->allow()->everything();                              // superuser
Valkyrie::allow($user, $office)->to('approve');            // limited to one scope
```

`$user->allow()` and `Valkyrie::allow($user)` are the same thing; the trait forwards to the facade.

### Roles

```php
$role = Valkyrie::role('manager', 'Manager');              // find-or-create the definition
Valkyrie::role('lead', scope: $office);                    // a definition for one scope only
Valkyrie::allow($role)->toManage(Report::class);           // grant to the role
$user->assign('manager');                                  // hold it globally
$user->assign('manager', $office);                         // hold it inside one scope
$user->retract('manager', $office);                        // take it away again
```

### Forbid and revoke

```php
$user->forbid()->to('delete', Report::class);              // hard deny — beats every allow
$user->forbid()->everything();                             // ban the user from everything
Valkyrie::disallow($user)->to('update', $report);          // remove an allow
Valkyrie::unforbid($user)->to('delete', Report::class);    // remove a forbid
Valkyrie::unforbid($user)->everything();                   // lift the ban
```

### Ownership

```php
Valkyrie::ownedVia(Report::class, 'author_id');            // default attribute is user_id
Valkyrie::ownedVia(Report::class, fn ($report, $user) => $report->team_id === $user->team_id);
```

## 2. Check access

```php
$user->can('update', $report);                             // Laravel-native, via the Gate bridge
Gate::allows('update', $report);                           // same
Route::put('/reports/{report}', ...)->can('update', 'report');
Route::get('/admin', ...)->middleware('role:system-admin');   // any-of
Route::get('/posts', ...)->middleware('ability:view-posts,edit-posts');

Valkyrie::can($user, 'approve', on: $office);              // explicit scope
Valkyrie::check($user, 'approve');                         // raw tri-state: true | false | null

$user->isA('manager');                                     // holds the role anywhere
$user->isA('manager', $office);                            // globally or in that scope

User::whereCan('view', Report::class)->get();              // query actors
User::whereIs('manager', $office)->get();
```

```blade
@role('manager') ... @else ... @endrole
@unlessrole('manager') ... @endrole
```

Surface caveats:

- Gate surfaces (`$user->can()`, `@can`, policies-style checks) carry **no scope** — unless the entity implements `ScopedEntity`, in which case its `accessScope()` applies automatically.
- `role:` middleware passes for a role held in **any** scope; `ability:` middleware matches **global** grants only.
- `whereCan()` excludes owner-only grants by design.

## 3. Which rule wins

The resolution rule, in order, is absolute:

1. **Any matching forbid → denied** (`false`). Policies never run.
2. Otherwise **any matching allow → allowed** (`true`).
3. Otherwise **no opinion** (`null`) — the check falls through to your policies and gate definitions.

Matching semantics: global grants apply inside every scope; scoped grants apply only to checks in exactly that scope (never inherited, never globally). An ability named `*` matches every name minus its `except` list; an entity type of `*` matches every model. Owner-only rows (allow *and* forbid) only match instances the actor owns.

| Given | Check | Result | Why |
|---|---|---|---|
| `allow($user)->to('edit', Report::class)` | `$user->can('edit', $report)` | ✅ | class-level grant covers every instance |
| `allow($user)->to('edit', Report::class)` | `$user->can('edit')` | ⚪ | grant targets `Report`; the check names no entity |
| `allow($user)->to('edit', $report)` | `$user->can('edit', Report::class)` | ⚪ | instance grant never covers a class-level check |
| `allow($user, $office)->to('approve')` | `Valkyrie::can($user, 'approve')` | ⚪ | scoped grant never matches a global check |
| `allow($user, $office)->to('approve')` | `can($user, 'approve', on: $office)` | ✅ | exact scope match |
| `allow($user, $office)->to('approve')` | `can($user, 'approve', on: $otherOffice)` | ⚪ | scopes match exactly, nothing is inherited |
| `allow($user)->to('approve')` | `can($user, 'approve', on: $office)` | ✅ | global grants apply inside every scope |
| `assign('manager', $user, $office)` + role allows `approve` | `can($user, 'approve', on: $office)` | ✅ | role held in the checked scope |
| `assign('manager', $user, $office)` + role allows `approve` | `Valkyrie::can($user, 'approve')` | ⚪ | the assignment is scoped; a global check cannot see it |
| `assign('manager', $user, $office)` + role allows `approve` on `Report::class` + `Report implements ScopedEntity` (returns its office) | `$user->can('approve', $report)` | ✅ | the entity carries its own scope into the Gate check |
| `allow($user)->toOwn(Report::class)` | `$user->can('edit', $ownReport)` | ✅ | actor owns the instance (`user_id` by default) |
| `allow($user)->toOwn(Report::class)` | `$user->can('edit', $theirReport)` | ⚪ | owner-only grant, actor does not own it |
| role allows `delete` + `forbid($user)->to('delete', Report::class)` | `$user->can('delete', $report)` | ⛔ | a forbid beats every allow, wherever the allow comes from |
| `forbid($user)->everything()` | any check at all | ⛔ | the ban: a wildcard forbid matches every ability on every entity, and policies never run |
| `allow($user)->everything(except: ['impersonate'])` | `$user->can('impersonate')` | ⚪ | `except` carves out of the wildcard — it is not a forbid |
| nothing in Valkyrie, `ReportPolicy::view()` returns `true` | `$user->can('view', $report)` | ✅ | `null` falls through, your policy decides |

**Legend:** ✅ allowed (`true`) · ⛔ hard denial (`false` — a forbid; policies never run) · ⚪ no opinion (`null` — falls through to policies; denied when nothing else allows it)
