# Grant abilities on owned records

Use this guide to let actors act only on records they own. For example, an author can edit their own posts but nobody else's.

**Before you start:** your actor model uses `HasRolesAndAbilities`.

## 1. Grant the owner-only ability

```php
$user->allow()->toOwn(Post::class);                             // every ability on owned posts
$user->allow()->toOwn(Post::class, only: ['view', 'update']);   // just these
$user->allow()->toOwn(Post::class, except: ['delete']);         // all but these
$user->allow()->toOwnEverything();                               // owned records of every model
$user->allow()->toOwnEverything(except: ['delete']);
```

Pass either `only` or `except`, not both. Passing both throws `InvalidArgumentException`.

Owner-only grants work on roles too, so every holder gets the ability on records they own:

```php
Valkyrie::allow(Valkyrie::role('author'))->toOwn(Post::class, only: ['update']);
```

## 2. Tell Valkyrie how ownership works

By default, an actor owns a record when the record's `user_id` equals the actor's key. If your model uses that column, you can skip this step.

For any other column, or for custom logic, register the rule once in a service provider's `boot()` method:

```php
// app/Providers/AppServiceProvider.php
use Signl\Valkyrie\Facades\Valkyrie;

public function boot(): void
{
    Valkyrie::ownedVia(Post::class, 'author_id');

    Valkyrie::ownedVia(Invoice::class, fn (Invoice $invoice, User $user): bool =>
        $invoice->team_id === $user->team_id
    );
}
```

The closure receives the record first and the actor second.

Attribute rules, including the default `user_id`, only match actors of your auth user model (`auth.providers.users.model`). A bare key can't tell user 5 from service account 5. For any other actor type, such as a service account, register a closure that checks the type itself:

```php
Valkyrie::ownedVia(Job::class, fn (Job $job, Model $actor): bool =>
    $actor instanceof ServiceAccount && $job->service_account_id === $actor->getKey()
);
```

A rule registered for a class also applies to its subclasses. If more than one registered class matches a record, the first one you registered wins.

## 3. Forbid on owned records (optional)

Owner-only rows work for forbids too. This forbid blocks deleting your own posts, while a broader allow still covers everyone else's:

```php
$moderator->allow()->toManage(Post::class);
$moderator->forbid()->toOwn(Post::class, only: ['delete']);
```

## Verify

```php
$user->can('update', $ownPost);       // true
$user->can('update', $someoneElses);  // false
Valkyrie::owns($user, $ownPost);      // true
```

## Watch out for

- **Checks need an instance.** `$user->can('update', Post::class)` never matches an owner-only grant, because Valkyrie can't tell who owns a class.
- **A missing or null owner column means "not owned".** Valkyrie returns `false` rather than throwing an error.
- **`whereCan()` skips owner-only grants.** See [Known limitations](../explanation/known-limitations.md).
