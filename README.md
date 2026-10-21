<div align="center">
    <h1>Laravel Valkyrie</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/signl-no/laravel-valkyrie"><img src="https://img.shields.io/packagist/v/signl-no/laravel-valkyrie.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/signl-no/laravel-valkyrie"><img src="https://img.shields.io/packagist/php-v/signl-no/laravel-valkyrie.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/signl-no/laravel-valkyrie"><img src="https://img.shields.io/packagist/dependency-v/signl-no/laravel-valkyrie/illuminate%2Fcontracts.svg?style=flat-square&label=Laravel" alt="Laravel versions"></a>
    <a href="https://github.com/signl-no/laravel-valkyrie/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/signl-no/laravel-valkyrie/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/signl-no/laravel-valkyrie"><img src="https://img.shields.io/packagist/dt/signl-no/laravel-valkyrie.svg?style=flat-square" alt="Total Downloads"></a>
</p>

> [!WARNING]
> This package is pre-1.0 and still being battle-tested. The API may change in breaking ways between 0.x releases. Feedback and issue reports are very welcome.

Valkyrie is a roles-and-abilities (RBAC) package for Laravel with a fluent API. It stores authorization rules in the database and answers every Gate check through a `Gate::before` bridge, so `$user->can()`, `@can`, policies and `->can()` route middleware work without changes. It also does a few things most RBAC packages don't:

- **Forbids always win.** An explicit forbid beats any allow, wherever the allow comes from.
- **Scoped grants.** Any grant or role assignment can be limited to one model, such as an office, a team or a project.
- **Ownership grants.** An ability can apply only to the records the actor owns.
- **Ready for multi-tenancy without dictating it.** Database-per-tenant, shared-table and hybrid setups all work, and Valkyrie leaves row scoping to your tenancy package.
- **A versioned cache.** Invalidation affects only what a write changed, and it waits for the surrounding transaction to commit.
- **Extensible models.** Subclass any model and swap it in with one line of config.

## Installation

Requires PHP 8.5+ and Laravel 13.

```bash
composer require signl-no/laravel-valkyrie
php artisan migrate
```

Then add the `Signl\Valkyrie\Concerns\HasRolesAndAbilities` trait to your `User` model.

## At a glance

```php
use Signl\Valkyrie\Facades\Valkyrie;

$user->allow()->to('view', Report::class);
$user->forbid()->to('delete', Report::class);

Valkyrie::allow(Valkyrie::role('manager'))->toManage(Report::class);
$user->assign('manager', $office);           // only inside this office

$user->can('update', $report);               // through Laravel's Gate
Valkyrie::can($user, 'approve', on: $office);
```

## Documentation

**New to Valkyrie?** Start with the [Getting started tutorial](docs/tutorials/getting-started.md).

| | |
|---|---|
| **How-to guides** | [Scopes](docs/how-to/scope-grants-to-a-model.md) · [Ownership](docs/how-to/grant-ownership-abilities.md) · [Routes and views](docs/how-to/protect-routes-and-views.md) · [Querying actors](docs/how-to/filter-queries-by-ability.md) · [Multi-tenancy](docs/how-to/set-up-multi-tenancy.md) · [Schema](docs/how-to/customize-the-schema.md) · [Extending models](docs/how-to/extend-the-models.md) · [Testing](docs/how-to/test-authorization-in-your-app.md) |
| **Reference** | [Cheat sheet](CHEATSHEET.md) · [API](docs/reference/api.md) · [Configuration](docs/reference/configuration.md) · [Middleware and Blade](docs/reference/middleware-and-blade.md) · [Events](docs/reference/events.md) · [Exceptions](docs/reference/exceptions.md) · [Commands](docs/reference/console-commands.md) · [Schema](docs/reference/database-schema.md) |
| **Explanation** | [Core concepts](docs/explanation/core-concepts.md) · [Rule resolution](docs/explanation/rule-resolution.md) · [Caching](docs/explanation/caching.md) · [Multi-tenancy design](docs/explanation/multi-tenancy-design.md) · [Known limitations](docs/explanation/known-limitations.md) |

The full index is in [docs/README.md](docs/README.md).

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Thank you for considering contributing to Laravel Valkyrie! Please read the [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security vulnerabilities

Please read [our security policy](.github/SECURITY.md) to learn how to report security vulnerabilities.

## Credits

- [Kim Eric Helle](https://github.com/signl-no)
- [All Contributors](../../contributors)

### Inspiration

Valkyrie builds on two excellent packages that shaped how authorization is done in the Laravel ecosystem:

- [silber/bouncer](https://github.com/JosephSilber/bouncer) inspired the fluent grant API and the database-backed abilities model.
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) is the gold standard for roles and permissions in Laravel.

## License

Laravel Valkyrie is open-source software licensed under the [MIT license](LICENSE.md).
