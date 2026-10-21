<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Resolution\AbilityCache;
use Workbench\App\Models\Tenant\TenantAbility;
use Workbench\App\Models\Tenant\TenantAssignedRole;
use Workbench\App\Models\Tenant\TenantPermission;
use Workbench\App\Models\Tenant\TenantRole;
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

function versionKey(?Model $actor = null): string
{
    $connection = DB::connection();
    $key = 'valkyrie:version:default|'.rawurlencode((string) $connection->getName()).'|'.rawurlencode($connection->getDatabaseName());

    return $actor === null ? $key : $key.':'.rawurlencode($actor->getMorphClass()).':'.rawurlencode((string) $actor->getKey());
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
    $before = Cache::get(versionKey());

    $role->title = 'Chief Editor';
    $role->save();

    expect(Cache::get(versionKey()))->toBe($before);
});

it('bumps for a definition name edit', function (): void {
    $role = Valkyrie::role('editor');
    $before = (int) Cache::get(versionKey(), 0);

    $role->name = 'reviewer';
    $role->save();

    expect((int) Cache::get(versionKey(), 0))->toBeGreaterThan($before);
});

it('always bumps on a definition delete', function (): void {
    $role = Valkyrie::role('editor');
    $before = (int) Cache::get(versionKey(), 0);

    $role->delete();

    expect((int) Cache::get(versionKey(), 0))->toBeGreaterThan($before);
});

it('defers the version bump to the commit of a wrapping transaction', function (): void {
    $user = User::factory()->create();
    $key = versionKey($user);

    DB::transaction(function () use ($user, $key): void {
        $user->allow()->to('edit-posts');

        expect(Cache::get($key))->toBeNull()
            ->and(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
    });

    expect((int) Cache::get($key, 0))->toBeGreaterThan(0);
});

it('discards the bump when the wrapping transaction rolls back', function (): void {
    $user = User::factory()->create();
    $key = versionKey($user);

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

it('drops rows read inside a rolled-back transaction', function (): void {
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user): void {
            $user->allow()->to('edit-posts');
            expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
    }

    expect(Valkyrie::check($user, 'edit-posts'))->toBeNull();
});

it('keeps an outer pending bump when a nested transaction rolls back', function (): void {
    $user = User::factory()->create();

    DB::transaction(function () use ($user): void {
        $user->allow()->to('edit-posts');

        try {
            DB::transaction(fn () => throw new RuntimeException('abort'));
        } catch (RuntimeException) {
        }

        expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue()
            ->and(Cache::get(versionKey($user)))->toBeNull();
    });

    expect(Cache::get(versionKey($user)))->not->toBeNull();
});

it('keeps memoized rows apart per database', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('admin');
    expect(Valkyrie::can($user, 'admin'))->toBeTrue();

    config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::setDefaultConnection('tenant');

    try {
        (require __DIR__.'/../../database/migrations/2026_09_24_101538_create_valkyrie_tables.php')->up();

        expect(Valkyrie::check($user, 'admin'))->toBeNull();
    } finally {
        DB::setDefaultConnection('testing');
    }
});

it('never resurrects revoked rows when a version key is evicted', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('admin');
    Cache::flush();
    app(AbilityCache::class)->flush();
    expect(Valkyrie::can($user, 'admin'))->toBeTrue();

    Valkyrie::disallow($user)->to('admin');
    Cache::forget(versionKey($user));
    app(AbilityCache::class)->flush();

    expect(Valkyrie::check($user, 'admin'))->toBeNull();
});

it('ignores rollbacks on connections Valkyrie does not use', function (): void {
    config()->set('database.connections.other', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user): void {
            $user->allow()->to('admin');

            try {
                DB::connection('other')->transaction(fn () => throw new RuntimeException('other'));
            } catch (RuntimeException) {
            }

            Valkyrie::can($user, 'admin');

            throw new RuntimeException('outer');
        });
    } catch (RuntimeException) {
    }

    app(AbilityCache::class)->flush();

    expect(Valkyrie::check($user, 'admin'))->toBeNull();
});

it('keys the cache by the connection the models use', function (): void {
    $databases = [tempnam(sys_get_temp_dir(), 'valkyrie-a'), tempnam(sys_get_temp_dir(), 'valkyrie-b')];
    $useTenant = function (string $database): void {
        config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true]);
        DB::purge('tenant');
        Schema::connection('tenant')->dropAllTables();
        DB::setDefaultConnection('tenant');

        try {
            (require __DIR__.'/../../database/migrations/2026_09_24_101538_create_valkyrie_tables.php')->up();
        } finally {
            DB::setDefaultConnection('testing');
        }
    };

    config()->set('valkyrie.models', [
        'role' => TenantRole::class,
        'ability' => TenantAbility::class,
        'permission' => TenantPermission::class,
        'assigned_role' => TenantAssignedRole::class,
    ]);

    try {
        $user = User::factory()->create();
        $useTenant($databases[0]);
        $user->allow()->to('admin');
        expect(Valkyrie::can($user, 'admin'))->toBeTrue();

        $useTenant($databases[1]);

        expect(Valkyrie::check($user, 'admin'))->toBeNull();
    } finally {
        DB::purge('tenant');
        array_map(unlink(...), $databases);
    }
});
