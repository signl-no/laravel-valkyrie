<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Resolution\AbilityCache;
use Workbench\App\Models\User;

function permissionQueryCount(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    DB::disableQueryLog();
    $table = config()->string('valkyrie.tables.permissions');

    return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], $table))->count();
}

it('memoizes resolution within a request', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');

    expect(permissionQueryCount(fn () => Valkyrie::can($user, 'edit-posts')))->toBe(1)
        ->and(permissionQueryCount(fn () => Valkyrie::can($user, 'edit-posts')))->toBe(0);
});

it('serves repeated checks from the store after a memo flush', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    Valkyrie::can($user, 'edit-posts');

    app(AbilityCache::class)->flush();

    expect(permissionQueryCount(fn () => Valkyrie::can($user, 'edit-posts')))->toBe(0);
});

it('queries again when the cache is disabled', function (): void {
    config()->set('valkyrie.cache.enabled', false);
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    Valkyrie::can($user, 'edit-posts');

    app(AbilityCache::class)->flush();

    expect(permissionQueryCount(fn () => Valkyrie::can($user, 'edit-posts')))->toBe(1);
});

it('invalidates only the granted actor on a direct grant', function (): void {
    [$a, $b] = User::factory()->count(2)->create();
    Valkyrie::ability('delete-posts');
    $a->allow()->to('edit-posts');
    $b->allow()->to('edit-posts');
    Valkyrie::can($a, 'edit-posts');
    Valkyrie::can($b, 'edit-posts');

    $a->allow()->to('delete-posts');
    app(AbilityCache::class)->flush();

    expect(permissionQueryCount(fn () => Valkyrie::can($a, 'edit-posts')))->toBe(1)
        ->and(permissionQueryCount(fn () => Valkyrie::can($b, 'edit-posts')))->toBe(0);
});

it('invalidates every actor on a role permission change', function (): void {
    [$a, $b] = User::factory()->count(2)->create();
    $role = Valkyrie::role('editor');
    $a->assign('editor');
    $b->assign('editor');
    Valkyrie::can($a, 'edit-posts');
    Valkyrie::can($b, 'edit-posts');

    Valkyrie::allow($role)->to('edit-posts');
    app(AbilityCache::class)->flush();

    expect(permissionQueryCount(fn () => Valkyrie::can($a, 'edit-posts')))->toBe(1)
        ->and(permissionQueryCount(fn () => Valkyrie::can($b, 'edit-posts')))->toBe(1)
        ->and(Valkyrie::can($a, 'edit-posts'))->toBeTrue();
});

it('bumps the cache when rows are written outside the facade', function (): void {
    $user = User::factory()->create();
    $ability = Valkyrie::ability('edit-posts');
    Valkyrie::can($user, 'edit-posts');

    $permission = $user->abilities()->newPivotStatement(); // not used; direct model write below
    Permission::query()->create([
        'ability_id' => $ability->getKey(),
        'entity_type' => $user->getMorphClass(),
        'entity_id' => $user->getKey(),
        'forbidden' => false,
    ]);

    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
});

it('invalidates the previous actor when a permission row is reassigned in place', function (): void {
    [$a, $b] = User::factory()->count(2)->create();
    $a->allow()->to('edit-posts');
    expect(Valkyrie::can($a, 'edit-posts'))->toBeTrue();

    Permission::query()->firstOrFail()->update(['entity_id' => $b->getKey()]);

    expect(Valkyrie::check($a, 'edit-posts'))->toBeNull()
        ->and(Valkyrie::can($b, 'edit-posts'))->toBeTrue();
});

it('invalidates role holders when a role permission row is reassigned to a user', function (): void {
    [$a, $b] = User::factory()->count(2)->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $a->assign('editor');
    expect(Valkyrie::can($a, 'edit-posts'))->toBeTrue();

    Permission::query()->firstOrFail()->update([
        'entity_type' => $b->getMorphClass(),
        'entity_id' => $b->getKey(),
    ]);

    expect(Valkyrie::check($a, 'edit-posts'))->toBeNull()
        ->and(Valkyrie::can($b, 'edit-posts'))->toBeTrue();
});

it('skips the bump for a title-only definition edit', function (): void {
    $role = Valkyrie::role('editor', 'Editor');
    $before = Cache::get('valkyrie:version:default');

    $role->title = 'Chief Editor';
    $role->save();

    expect(Cache::get('valkyrie:version:default'))->toBe($before);
});

it('bumps for a definition name edit', function (): void {
    $role = Valkyrie::role('editor');
    $before = (int) Cache::get('valkyrie:version:default', 0);

    $role->name = 'reviewer';
    $role->save();

    expect((int) Cache::get('valkyrie:version:default', 0))->toBeGreaterThan($before);
});

it('always bumps on a definition delete', function (): void {
    $role = Valkyrie::role('editor');
    $before = (int) Cache::get('valkyrie:version:default', 0);

    $role->delete();

    expect((int) Cache::get('valkyrie:version:default', 0))->toBeGreaterThan($before);
});

it('defers the version bump to the commit of a wrapping transaction', function (): void {
    $user = User::factory()->create();
    $key = 'valkyrie:version:default:'.$user->getMorphClass().':'.$user->getKey();

    DB::transaction(function () use ($user, $key): void {
        $user->allow()->to('edit-posts');

        expect(Cache::get($key))->toBeNull()
            ->and(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
    });

    expect((int) Cache::get($key, 0))->toBeGreaterThan(0);
});

it('discards the bump when the wrapping transaction rolls back', function (): void {
    $user = User::factory()->create();
    $key = 'valkyrie:version:default:'.$user->getMorphClass().':'.$user->getKey();

    try {
        DB::transaction(function () use ($user): void {
            $user->allow()->to('edit-posts');

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
    }

    expect(Cache::get($key))->toBeNull()
        ->and(Valkyrie::check($user, 'edit-posts'))->toBeNull();
});
