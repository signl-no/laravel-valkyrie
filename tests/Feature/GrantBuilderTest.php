<?php

declare(strict_types=1);

use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Support\Models;
use Workbench\App\Models\Office;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('grants a list of abilities with to', function (): void {
    $user = User::factory()->create();
    $permissions = $user->allow()->to(['edit-posts', 'delete-posts']);

    expect($permissions)->toHaveCount(2)
        ->and(Valkyrie::can($user, 'edit-posts'))->toBeTrue()
        ->and(Valkyrie::can($user, 'delete-posts'))->toBeTrue();
});

it('grants every ability on a model with toManage', function (): void {
    $user = User::factory()->create();
    $user->allow()->toManage(Post::class, except: ['delete']);

    expect(Valkyrie::can($user, 'edit', Post::class))->toBeTrue()
        ->and(Valkyrie::check($user, 'delete', Post::class))->toBeNull()
        ->and(Valkyrie::check($user, 'edit'))->toBeNull();
});

it('grants ownership of everything with toOwnEverything', function (): void {
    $user = User::factory()->create();
    $owned = Post::query()->create(['title' => 'mine', 'user_id' => $user->getKey()]);

    $user->allow()->toOwnEverything(except: ['delete']);

    expect(Valkyrie::can($user, 'edit', $owned))->toBeTrue()
        ->and(Valkyrie::check($user, 'delete', $owned))->toBeNull();
});

it('rejects toOwn with both only and except', function (): void {
    $user = User::factory()->create();

    $user->allow()->toOwn(Post::class, only: ['edit'], except: ['delete']);
})->throws(InvalidArgumentException::class, 'Pass either only or except');

it('removes an allow with disallow', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    Valkyrie::disallow($user)->to('edit-posts');

    expect(Valkyrie::check($user, 'edit-posts'))->toBeNull()
        ->and(Models::permission()::query()->count())->toBe(0);
});

it('removes a forbid with unforbid but keeps the allow', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->forbid()->to('edit-posts');
    Valkyrie::unforbid($user)->to('edit-posts');

    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
});

it('is idempotent for repeated grants', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->allow()->to('edit-posts');

    expect(Models::permission()::query()->count())->toBe(1);
});

it('updates the except list on an existing wildcard row', function (): void {
    $user = User::factory()->create();
    $user->allow()->everything(except: ['a']);
    $user->allow()->everything(except: ['b']);

    expect(Models::permission()::query()->count())->toBe(1)
        ->and(Valkyrie::check($user, 'b'))->toBeNull()
        ->and(Valkyrie::can($user, 'a'))->toBeTrue();
});

it('keeps scoped and global grants apart', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    Valkyrie::allow($user, $office)->to('edit-posts');

    expect(Valkyrie::check($user, 'edit-posts'))->toBeNull()
        ->and(Valkyrie::can($user, 'edit-posts', on: $office))->toBeTrue();
});

it('disallowing a missing grant is a no-op', function (): void {
    $user = User::factory()->create();

    expect(Valkyrie::disallow($user)->to('edit-posts'))->toHaveCount(0);
});

it('does not create abilities when removing unknown ones', function (): void {
    $user = User::factory()->create();

    Valkyrie::disallow($user)->to('never-granted');
    Valkyrie::unforbid($user)->toManage(Post::class);
    Valkyrie::disallow($user)->toOwn(Post::class, only: ['edit']);
    Valkyrie::unforbid($user)->everything();

    expect(Ability::query()->count())->toBe(0);
});

it('still removes an existing grant', function (): void {
    $user = User::factory()->create();
    $user->allow()->toOwn(Post::class, only: ['edit']);

    Valkyrie::disallow($user)->toOwn(Post::class, only: ['edit']);

    expect($user->getAbilities())->toBeEmpty();
});
