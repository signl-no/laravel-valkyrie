# Console commands

## `valkyrie:grant-superuser`

```bash
php artisan valkyrie:grant-superuser {email}
```

Grants a user every ability. Use it to regain access after an admin locks themselves out, or to set up the first admin.

| Argument | Description |
|---|---|
| `email` | The email address of the user. |

What it does:

1. Finds the user by `email` on the model in `auth.providers.users.model`.
2. Finds or creates the role `valkyrie.superuser.role` (default `system-admin`), titled `valkyrie.superuser.title` (default `System Administrator`).
3. Grants that role `everything()`.
4. Assigns the role to the user globally.

It's safe to run more than once.

| Exit code | When |
|---|---|
| `0` | The user now holds the role. |
| `1` | No user has that email address. |

> [!NOTE]
> The superuser role grants `*` on `*`, but a direct `forbid()` on the user still wins.
