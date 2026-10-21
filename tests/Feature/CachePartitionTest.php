<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Signl\Valkyrie\Contracts\CachePartition;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Resolution\AbilityCache;
use Signl\Valkyrie\Resolution\DefaultCachePartition;
use Signl\Valkyrie\Tests\Fakes\FakeCachePartition;
use Workbench\App\Models\User;

it('defaults to a single partition', function (): void {
    expect(app(CachePartition::class))->toBeInstanceOf(DefaultCachePartition::class)
        ->and(app(CachePartition::class)->key())->toBe('default');
});

it('resolves a custom partition class from the config', function (): void {
    config()->set('valkyrie.cache.partition', FakeCachePartition::class);
    $this->app->forgetInstance(CachePartition::class);

    expect(app(CachePartition::class))->toBeInstanceOf(FakeCachePartition::class);
});

it('isolates cached ability rows between partitions', function (): void {
    $partition = new FakeCachePartition('tenant-a');
    app()->instance(CachePartition::class, $partition);

    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();

    // Delete behind the cache's back: query-builder writes fire no model events.
    DB::table(config()->string('valkyrie.tables.permissions'))->delete();
    app(AbilityCache::class)->flush();

    // The same partition still serves the stale entry from the shared store.
    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();

    // Another partition misses it and reads the database instead.
    $partition->key = 'tenant-b';
    expect(Valkyrie::check($user, 'edit-posts'))->toBeNull();
});

it('rejects a partition class that does not implement the contract', function (): void {
    config()->set('valkyrie.cache.partition', stdClass::class);
    $this->app->forgetInstance(CachePartition::class);

    app(CachePartition::class);
})->throws(TypeError::class);
