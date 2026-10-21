<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Signl\Valkyrie\Http\Middleware\EnsureHasAbility;
use Signl\Valkyrie\Http\Middleware\EnsureHasRole;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\Ownership\OwnershipResolver;
use Signl\Valkyrie\Resolution\AbilityCache;
use Signl\Valkyrie\Resolution\AbilityResolver;
use Signl\Valkyrie\ValkyrieManager;

it('merges the package config', function (): void {
    expect(config('valkyrie.tables.abilities'))->toBe('valkyrie_abilities')
        ->and(config('valkyrie.models.role'))->toBe(Role::class)
        ->and(config('valkyrie.cache.enabled'))->toBeTrue();
});

it('resolves the services as shared singletons', function (): void {
    expect(app(ValkyrieManager::class))->toBe(app(ValkyrieManager::class))
        ->and(app(AbilityCache::class))->toBe(app(AbilityCache::class))
        ->and(app(AbilityResolver::class))->toBe(app(AbilityResolver::class))
        ->and(app(OwnershipResolver::class))->toBe(app(OwnershipResolver::class));
});

it('registers the morph map aliases', function (): void {
    expect(Relation::getMorphedModel('role'))->toBe(Role::class)
        ->and(Relation::getMorphedModel('ability'))->toBe(Ability::class);
});

it('loads the package migrations', function (): void {
    expect(Schema::hasTable('valkyrie_abilities'))->toBeTrue()
        ->and(Schema::hasTable('valkyrie_roles'))->toBeTrue()
        ->and(Schema::hasTable('valkyrie_assigned_roles'))->toBeTrue()
        ->and(Schema::hasTable('valkyrie_permissions'))->toBeTrue();
});

it('registers the grant-superuser command', function (): void {
    expect(Artisan::all())->toHaveKey('valkyrie:grant-superuser');
});

it('exposes the config publish tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'laravel-valkyrie-config');

    expect($paths)->not->toBeEmpty()
        ->and(str_replace('\\', '/', array_values($paths)[0]))->toEndWith('config/valkyrie.php');
});

it('registers the middleware aliases', function (): void {
    $middleware = app(Router::class)->getMiddleware();

    expect($middleware)->toHaveKey('role')
        ->and($middleware['role'])->toBe(EnsureHasRole::class)
        ->and($middleware['ability'])->toBe(EnsureHasAbility::class);
});
