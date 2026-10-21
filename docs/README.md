# Valkyrie documentation

The documentation is organized by what you need right now.

## Tutorials: learn by doing

- [Getting started](tutorials/getting-started.md) walks through installing Valkyrie and building direct grants, roles, forbids, scoped roles and owner-only abilities in a small reporting app.

## How-to guides: solve a specific problem

- [Scope grants to a model](how-to/scope-grants-to-a-model.md) limits abilities and roles to an office, team or project.
- [Grant abilities on owned records](how-to/grant-ownership-abilities.md) covers "edit your own posts" and custom ownership rules.
- [Protect routes and views](how-to/protect-routes-and-views.md) covers middleware, `->can()` and Blade directives.
- [Find actors by ability or role](how-to/filter-queries-by-ability.md) uses the `whereCan` and `whereIs` query scopes.
- [Set up multi-tenancy](how-to/set-up-multi-tenancy.md) covers database per tenant, shared tables and hybrid setups.
- [Customize the schema](how-to/customize-the-schema.md) renames tables and changes columns.
- [Extend the models](how-to/extend-the-models.md) shows how to subclass, add observers and register your models.
- [Test authorization in your app](how-to/test-authorization-in-your-app.md) covers grants in tests, factories and events.

## Reference: look something up

- [Cheat sheet](../CHEATSHEET.md) is a one-page summary with an example table of outcomes.
- [API](reference/api.md) covers the facade, grant builder, trait, contracts and models.
- [Configuration](reference/configuration.md) lists every config key, environment variable and publish tag.
- [Middleware and Blade](reference/middleware-and-blade.md)
- [Events](reference/events.md)
- [Exceptions](reference/exceptions.md)
- [Console commands](reference/console-commands.md)
- [Database schema](reference/database-schema.md)

## Explanation: understand the design

- [Core concepts](explanation/core-concepts.md) describes actors, abilities, permissions, roles, scopes and ownership.
- [Rule resolution](explanation/rule-resolution.md) covers the Gate bridge, why forbids always win, and how matching works.
- [Caching](explanation/caching.md) covers versioned invalidation and after-commit bumps.
- [Multi-tenancy design](explanation/multi-tenancy-design.md) explains why Valkyrie supports tenancy without dictating it.
- [Known limitations](explanation/known-limitations.md) lists the deliberate trade-offs and how to work around them.
