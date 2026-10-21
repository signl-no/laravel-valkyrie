# Getting started with Valkyrie

In this tutorial you will add role-based access control to a small reporting app. By the end, your users will have direct abilities, a shared role, a forbid that overrides that role, a role limited to one office, and an ability that only applies to reports they wrote.

You will check every step in `php artisan tinker`, so you can see each rule take effect as you add it.

**You need:** a Laravel 13 application on PHP 8.5+, with a working database connection and the default `App\Models\User` model.

**Time:** about 20 minutes.

---

## 1. Install the package

```bash
composer require signl-no/laravel-valkyrie
php artisan migrate
```

The migration creates four tables: `valkyrie_abilities`, `valkyrie_roles`, `valkyrie_assigned_roles` and `valkyrie_permissions`. You don't need to register a service provider or a facade, because Laravel discovers both.

## 2. Make users actors

Add the `HasRolesAndAbilities` trait to your user model:

```php
// app/Models/User.php
use Signl\Valkyrie\Concerns\HasRolesAndAbilities;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use HasRolesAndAbilities;

    // ...
}
```

Valkyrie now answers Laravel's authorization checks (`$user->can()`, `@can`, `Gate::allows()`) for users.

## 3. Create offices and reports

The app needs something to protect. Create two models with their migrations:

```bash
php artisan make:model Office -m
php artisan make:model Report -m
```

Fill in the migrations:

```php
// database/migrations/xxxx_create_offices_table.php
Schema::create('offices', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

```php
// database/migrations/xxxx_create_reports_table.php
Schema::create('reports', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('office_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->timestamps();
});
```

Make both models fillable:

```php
// app/Models/Office.php
#[Fillable(['name'])]
class Office extends Model {}
```

```php
// app/Models/Report.php
#[Fillable(['title', 'office_id', 'user_id'])]
class Report extends Model
{
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
```

Run the migrations, then open tinker and create some data. Keep this tinker session open for the rest of the tutorial.

```bash
php artisan migrate
php artisan tinker
```

```php
use App\Models\{User, Office, Report};
use Signl\Valkyrie\Facades\Valkyrie;

$alice = User::factory()->create(['name' => 'Alice']);
$bob   = User::factory()->create(['name' => 'Bob']);

$oslo   = Office::create(['name' => 'Oslo']);
$bergen = Office::create(['name' => 'Bergen']);

$osloReport   = Report::create(['title' => 'Q3 Oslo',   'office_id' => $oslo->id,   'user_id' => $bob->id]);
$bergenReport = Report::create(['title' => 'Q3 Bergen', 'office_id' => $bergen->id, 'user_id' => $alice->id]);
```

## 4. Grant a direct ability

Allow Alice to view reports:

```php
$alice->allow()->to('view', Report::class);
```

Check it:

```php
$alice->can('view', $osloReport);
// = true

$alice->can('update', $osloReport);
// = false

$bob->can('view', $osloReport);
// = false
```

The grant targets the `Report` class, so it covers every report. Alice hasn't been given `update`, and Bob hasn't been given anything.

## 5. Share abilities through a role

Granting abilities one user at a time doesn't scale. Create an `editor` role, give it every ability on reports, and assign it to both users:

```php
$editor = Valkyrie::role('editor', 'Editor');
Valkyrie::allow($editor)->toManage(Report::class);

$alice->assign('editor');
$bob->assign('editor');
```

```php
$bob->can('update', $osloReport);
// = true

$bob->can('delete', $osloReport);
// = true

$bob->isA('editor');
// = true
```

`toManage()` grants the wildcard ability `*` on `Report`, so it matches `update`, `delete` and any other ability name you check on a report.

## 6. Forbid an ability

Alice is an editor, but she shouldn't be able to delete reports. Forbid it:

```php
$alice->forbid()->to('delete', Report::class);
```

```php
$alice->can('delete', $osloReport);
// = false

$alice->can('update', $osloReport);
// = true

$bob->can('delete', $osloReport);
// = true
```

The forbid wins over the allow that Alice gets from her role. It only affects Alice, so Bob keeps his access.

To see what Valkyrie decided before Laravel turns it into a boolean, use `check()`:

```php
Valkyrie::check($alice, 'delete', $osloReport);
// = false   (a forbid matched)

Valkyrie::check($alice, 'publish');
// = null    (no rule matched; Laravel falls through to your policies)
```

## 7. Limit a role to one office

Carol approves reports, but only for Oslo. Create an `approver` role and assign it to her inside the Oslo office:

```php
$carol = User::factory()->create(['name' => 'Carol']);

$approver = Valkyrie::role('approver', 'Approver');
Valkyrie::allow($approver)->to('approve', Report::class);

$carol->assign('approver', $oslo);
```

Ask Valkyrie directly, passing the scope:

```php
Valkyrie::can($carol, 'approve', Report::class, on: $oslo);
// = true

Valkyrie::can($carol, 'approve', Report::class, on: $bergen);
// = false
```

A plain `$carol->can('approve', $osloReport)` returns `false`, because Laravel's Gate has no way to pass a scope. Fix that by letting each report declare its own scope. Implement `ScopedEntity` on `Report`:

```php
// app/Models/Report.php
use Signl\Valkyrie\Contracts\ScopedEntity;

#[Fillable(['title', 'office_id', 'user_id'])]
class Report extends Model implements ScopedEntity
{
    public function accessScope(): ?Model
    {
        return $this->office;
    }

    // office() relation as before
}
```

Restart tinker so it loads the changed class, and fetch the records again:

```php
use App\Models\{User, Office, Report};
use Signl\Valkyrie\Facades\Valkyrie;

$carol        = User::firstWhere('name', 'Carol');
$osloReport   = Report::firstWhere('title', 'Q3 Oslo');
$bergenReport = Report::firstWhere('title', 'Q3 Bergen');
```

```php
$carol->can('approve', $osloReport);
// = true

$carol->can('approve', $bergenReport);
// = false
```

Each report now carries its office into every Gate check, so `@can`, policies and `authorize()` all respect the scope.

## 8. Grant an ability on owned records only

Carol should be able to update the reports she wrote, and no others:

```php
$carol->allow()->toOwn(Report::class, only: ['update']);

$carolsReport = Report::create(['title' => 'Carol’s notes', 'office_id' => $bergenReport->office_id, 'user_id' => $carol->id]);
```

```php
$carol->can('update', $carolsReport);
// = true

$carol->can('update', $bergenReport);
// = false
```

By default, Valkyrie treats a record's `user_id` as its owner.

## What you built

You now have:

- a **direct grant** (Alice can view reports);
- a **role** that bundles abilities for several users (`editor`);
- a **forbid** that overrides a role (Alice can't delete);
- a **scoped role assignment**, carried into Gate checks by `ScopedEntity` (Carol approves in Oslo only);
- an **owner-only grant** (Carol updates her own reports).

## Next steps

- Protect routes and Blade views: [Protect routes and views](../how-to/protect-routes-and-views.md)
- Use a column other than `user_id` for ownership: [Grant ownership abilities](../how-to/grant-ownership-abilities.md)
- Find out why forbids always win and how scopes match: [Rule resolution](../explanation/rule-resolution.md)
- Every method in one place: [API reference](../reference/api.md)
