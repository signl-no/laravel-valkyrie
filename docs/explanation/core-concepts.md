# Core concepts

Valkyrie stores authorization rules as rows in four tables and evaluates them on every Gate check. This page describes what those rows represent and how they relate. It covers the model, not the API.

## Actors

An **actor** is any Eloquent model that uses `HasRolesAndAbilities`. Usually that's your user model, but a service account, an API client or a device model works just as well. Valkyrie stores actors through polymorphic columns, so one installation can serve several actor types.

Roles are actors too, in one sense: you grant abilities to a role exactly as you grant them to a user (`Valkyrie::allow($role)`). The difference is that a role's abilities reach the users who hold it, and a user's abilities stay with that user.

## Abilities

An **ability** is something an actor can be allowed or forbidden to do. Its name is a free-form string such as `view`, `export-reports` or `approve`. Valkyrie has no fixed list: the abilities table fills up as you grant, and checking a name that has never been granted simply finds no rule.

An ability optionally targets an **entity**:

| Target | Example | Matches checks on |
|---|---|---|
| None | `to('export-reports')` | Checks that name no entity |
| A model class | `to('view', Report::class)` | Any `Report`, or the `Report` class |
| One record | `to('update', $report)` | That `Report` only |
| Every model | `everything()` | Anything |

An ability can also be **owner-only**, meaning it matches only records the actor owns.

## The wildcard

`*` means "every". An ability named `*` matches every ability name, and an entity type of `*` matches every model. `toManage(Report::class)` is `*` on `Report`, and `everything()` is `*` on `*`.

A grant on a wildcard can carry an **`except`** list, which removes names from the wildcard. That's different from a forbid. A name in `except` is simply not covered, so the check returns "no opinion" and falls through to your policies.

## Permissions

A **permission** is one row linking an ability to an actor or a role. It's either an **allow** or a **forbid**, and it's either global or limited to one scope. Every call to `allow()` or `forbid()` writes permission rows. Every call to `disallow()` or `unforbid()` removes them.

## Roles

A **role** is a named bundle of permissions. Holding a role gives an actor every permission granted to it.

A role **assignment** links a role to an actor, optionally in one scope. The same user can hold `manager` globally, or only in Oslo, or in Oslo and Bergen through two assignments.

A role **definition** can itself be scoped: `Valkyrie::role('office-admin', scope: $oslo)` creates a role that exists only for Oslo. Several offices can then each have an `office-admin` with different abilities.

## Scopes

A **scope** is any Eloquent model that a permission or assignment is limited to, such as an office, a team or a project. `null` means global. Scopes give you per-organization access without separate databases.

Scope matching is exact. A grant in a parent scope doesn't apply in a child scope, and a global check never sees scoped grants. Global grants do apply inside every scope. [Rule resolution](rule-resolution.md) explains why.

## Ownership

**Ownership** is the relationship between an actor and a record, such as "Alice wrote this report". It's decided at check time by a rule you register per model. The default rule compares the record's `user_id` with the actor's key, and applies only to actors of your auth user model. Other actor types need a closure rule. Owner-only abilities consult this rule, so one grant can cover "your own posts" for every author.

## How the pieces fit

```
Actor ──holds──▶ Role ──has──▶ Permission ──for──▶ Ability ──targets──▶ Entity
  │              (scoped?)     (allow/forbid,       (name, *,            (class, record,
  │                             scoped?, except)     owner-only?)         *, or none)
  └──────────────────has──────▶ Permission
```

A check collects every permission the actor can reach, either directly or through roles held in the checked scope. It keeps those whose ability matches the name and entity, and then applies the [resolution rule](rule-resolution.md).
