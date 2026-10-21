<?php

declare(strict_types=1);

use Signl\Valkyrie\Facades\Valkyrie;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('has no opinion when nothing is granted', function (): void {
    $user = User::factory()->create();

    expect(Valkyrie::check($user, 'edit-posts'))->toBeNull()
        ->and(Valkyrie::can($user, 'edit-posts'))->toBeFalse()
        ->and(Valkyrie::cannot($user, 'edit-posts'))->toBeTrue();
});

it('allows a granted ability', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');

    expect(Valkyrie::check($user, 'edit-posts'))->toBeTrue()
        ->and(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
});

it('lets forbid beat allow', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->forbid()->to('edit-posts');

    expect(Valkyrie::check($user, 'edit-posts'))->toBeFalse();
});

it('matches the wildcard ability name', function (): void {
    $user = User::factory()->create();
    $user->allow()->everything();

    expect(Valkyrie::can($user, 'anything-at-all'))->toBeTrue()
        ->and(Valkyrie::can($user, 'edit', Post::class))->toBeTrue();
});

it('excludes names on a wildcard except list', function (): void {
    $user = User::factory()->create();
    $user->allow()->everything(except: ['delete-posts']);

    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue()
        ->and(Valkyrie::check($user, 'delete-posts'))->toBeNull();
});

it('scopes a grant to a model class', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit', Post::class);

    expect(Valkyrie::can($user, 'edit', Post::class))->toBeTrue()
        ->and(Valkyrie::check($user, 'edit'))->toBeNull()
        ->and(Valkyrie::can($user, 'edit', Post::query()->create(['title' => 'a'])))->toBeTrue();
});

it('scopes a grant to one instance', function (): void {
    $user = User::factory()->create();
    $mine = Post::query()->create(['title' => 'mine']);
    $other = Post::query()->create(['title' => 'other']);
    $user->allow()->to('edit', $mine);

    expect(Valkyrie::can($user, 'edit', $mine))->toBeTrue()
        ->and(Valkyrie::check($user, 'edit', $other))->toBeNull()
        ->and(Valkyrie::check($user, 'edit', Post::class))->toBeNull();
});

it('honors only_owned grants through the default user_id attribute', function (): void {
    $user = User::factory()->create();
    $owned = Post::query()->create(['title' => 'mine', 'user_id' => $user->getKey()]);
    $foreign = Post::query()->create(['title' => 'other', 'user_id' => $user->getKey() + 1]);

    $user->allow()->toOwn(Post::class);

    expect(Valkyrie::can($user, 'edit', $owned))->toBeTrue()
        ->and(Valkyrie::check($user, 'edit', $foreign))->toBeNull()
        ->and(Valkyrie::check($user, 'edit', Post::class))->toBeNull();
});

it('honors ownedVia attribute registration', function (): void {
    $user = User::factory()->create();
    Valkyrie::ownedVia(Post::class, 'author_id');
    $owned = Post::query()->create(['title' => 'mine', 'author_id' => $user->getKey()]);

    $user->allow()->toOwn(Post::class);

    expect(Valkyrie::can($user, 'edit', $owned))->toBeTrue()
        ->and(Valkyrie::owns($user, $owned))->toBeTrue();
});

it('honors ownedVia closure registration', function (): void {
    $user = User::factory()->create();
    Valkyrie::ownedVia(Post::class, fn (Post $post, User $actor): bool => $post->title === "owned-by-{$actor->getKey()}");
    $owned = Post::query()->create(['title' => "owned-by-{$user->getKey()}"]);

    $user->allow()->toOwn(Post::class);

    expect(Valkyrie::can($user, 'edit', $owned))->toBeTrue();
});

it('limits toOwn to the given names with only', function (): void {
    $user = User::factory()->create();
    $owned = Post::query()->create(['title' => 'mine', 'user_id' => $user->getKey()]);

    $user->allow()->toOwn(Post::class, only: ['edit', 'view']);

    expect(Valkyrie::can($user, 'edit', $owned))->toBeTrue()
        ->and(Valkyrie::can($user, 'view', $owned))->toBeTrue()
        ->and(Valkyrie::check($user, 'delete', $owned))->toBeNull();
});

it('resolves abilities granted through a held role', function (): void {
    $user = User::factory()->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $user->assign('editor');

    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
});

it('lets a forbid on the actor beat an allow through a role', function (): void {
    $user = User::factory()->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $user->assign('editor');
    $user->forbid()->to('edit-posts');

    expect(Valkyrie::check($user, 'edit-posts'))->toBeFalse();
});
