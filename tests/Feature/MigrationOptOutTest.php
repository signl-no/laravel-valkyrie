<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Tests\Feature;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Signl\Valkyrie\Tests\MigrationsDisabledTestCase;

final class MigrationOptOutTest extends MigrationsDisabledTestCase
{
    public function test_it_skips_loading_the_package_migrations_when_disabled(): void
    {
        $paths = collect($this->app->make(Migrator::class)->paths());
        $packagePath = realpath(__DIR__.'/../../database/migrations');

        $this->assertFalse($paths->contains(fn (string $path): bool => realpath($path) === $packagePath));
        $this->assertFalse(Schema::hasTable('valkyrie_abilities'));
    }

    public function test_it_still_exposes_the_publish_tag_when_loading_is_disabled(): void
    {
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(null, 'laravel-valkyrie-migrations'));
    }
}
