<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

it('loads the package migrations by default', function (): void {
    $paths = app(Migrator::class)->paths();
    $packagePath = realpath(__DIR__.'/../../database/migrations');

    expect(collect($paths)->contains(fn (string $path): bool => realpath($path) === $packagePath))->toBeTrue();
});

it('publishes the migration under its original filename', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'laravel-valkyrie-migrations');

    expect($paths)->toHaveCount(1)
        ->and(array_keys($paths)[0])->toEndWith('2026_09_24_101538_create_valkyrie_tables.php')
        ->and(str_replace('\\', '/', array_values($paths)[0]))->toEndWith('migrations/2026_09_24_101538_create_valkyrie_tables.php');
});
