# Rule resolution

This page explains how Valkyrie decides an authorization check and why it was designed that way. For a table of example outcomes, see the [cheat sheet](../../CHEATSHEET.md#3-which-rule-wins).

## Valkyrie sits in front of Laravel's Gate

Valkyrie doesn't replace Laravel's authorization. It registers a single `Gate::before` callback. For every check, Laravel asks Valkyrie first:

- If the user isn't an actor (a guest, or a model without the trait), Valkyrie returns `null` and Laravel continues as if Valkyrie weren't installed.
- Otherwise Valkyrie evaluates the stored rules and returns `true`, `false` or `null`.

Laravel treats a non-null answer from a `before` callback as final. A `null` means "carry on", so your `Gate::define()` callbacks and policies run as usual.

The bridge reads the first Gate argument as the entity when it's a model instance or a model class name, and ignores the other arguments.

This is why everything in Laravel's authorization works without changes: `$user->can()`, `@can`, `authorize()`, `->can()` route middleware and `Gate::allows()`. It's also why Valkyrie can be adopted gradually: an ability Valkyrie knows nothing about returns `null` and reaches your existing policy.

## The three answers

For a given check, Valkyrie gathers every permission row the actor can reach: rows granted to the actor directly, and rows granted to roles the actor holds in the checked scope. It keeps those whose ability matches the requested name and entity, then decides:

1. **If any matching row is a forbid, the answer is `false`.**
2. **Otherwise, if any matching row is an allow, the answer is `true`.**
3. **Otherwise the answer is `null`.**

## Why forbids always win

In many RBAC systems, the result depends on where a rule came from: a direct grant overrides a role, or a more specific rule beats a general one. That makes the outcome hard to predict once users hold several roles.

Valkyrie removes the ordering. A forbid has no priority to weigh against an allow, and its source (direct, role, wildcard, scoped) doesn't matter. If it matches, the check fails. This has some useful results:

- **Bans are one line.** `$user->forbid()->everything()` denies every check, including ones a policy would allow, because a `false` from a `before` callback stops the Gate.
- **Roles can't accidentally widen access.** Adding a role to a user can never undo a forbid.
- **Audits are simple.** To know whether a forbid is in force, you only need to find it.

The trade-off is that you can't "allow despite a forbid". The design treats that as a feature: if an exception to a forbid is needed, narrow the forbid instead.

## `false` and `null` are different

`false` is a hard denial: Valkyrie found a forbid, and your policies never run. `null` means Valkyrie has no opinion: nothing matched, and the decision passes to your policies and gate definitions. If nothing else allows the check either, Laravel denies it.

`Valkyrie::can()` turns both into `false`. Use `Valkyrie::check()` when you need to tell them apart.

The `except` list on a wildcard produces `null`, not `false`. `everything(except: ['impersonate'])` doesn't cover `impersonate`, but it doesn't forbid it either. A policy can still allow it.

## How entities match

An ability row matches a check when:

- its name equals the checked name, or is `*` and the checked name isn't in its `except` list;
- its entity type equals the checked entity's morph class, or is `*`;
- it has no entity ID, or its entity ID equals the checked record's key.

So a class-level grant covers every record and class-level checks, and a record-level grant covers only that record. A grant with no entity covers only checks that name no entity. `to('edit')` doesn't satisfy `can('edit', $report)`.

## How scopes match

A check runs in one scope. When the entity implements `ScopedEntity`, that scope is whatever `accessScope()` returns, and `null` means global. Otherwise it's the explicit `on:` argument, or global when there isn't one.

A `ScopedEntity` never falls back to `on:`. If it did, a record with no scope could be opened through a grant in any scope the caller chose to pass. Valkyrie then considers:

- permission rows that are global **or** in exactly that scope;
- role assignments that are global **or** in exactly that scope.

Scopes don't inherit. A grant in a parent office doesn't reach a child office, and a check in the child doesn't look upward. Valkyrie doesn't know how your scope models relate. Office trees, nested teams and project hierarchies all differ, and guessing would grant access nobody asked for. If you want inheritance, grant in each scope, or have `accessScope()` return the level where you manage access.

Global grants apply inside every scope, because a global grant states that something holds everywhere.

## How ownership matches

Owner-only rows, both allows and forbids, match only when the checked entity is a record and the ownership rule says the actor owns it. Valkyrie evaluates ownership after the name, entity and scope have matched, so the extra lookup only runs for rows that could apply.

## Order of evaluation

Valkyrie doesn't evaluate rules in priority order, because none exists. It filters the rows that match and looks for a forbid. Asking for the same ability in the same scope always gives the same answer, regardless of grant order, role order or the order in which rows were written.
