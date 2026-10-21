<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('answers $user->can() through the gate', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');

    expect($user->can('edit-posts'))->toBeTrue()
        ->and($user->cannot('delete-posts'))->toBeTrue();
});

it('answers Gate::allows() for the authenticated user', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $this->actingAs($user);

    expect(Gate::allows('edit-posts'))->toBeTrue()
        ->and(Gate::denies('delete-posts'))->toBeTrue();
});

it('normalizes a class-string entity argument', function (): void {
    $user = User::factory()->create();
    $user->allow()->toManage(Post::class);

    expect($user->can('edit', Post::class))->toBeTrue()
        ->and($user->can('edit', Post::query()->create(['title' => 'a'])))->toBeTrue();
});

it('lets a forbid win over a gate definition', function (): void {
    Gate::define('edit-posts', fn (User $user): bool => true);
    $user = User::factory()->create();
    $user->forbid()->to('edit-posts');

    expect($user->can('edit-posts'))->toBeFalse();
});

it('falls through to gate definitions when valkyrie has no opinion', function (): void {
    Gate::define('defined-elsewhere', fn (User $user): bool => true);
    $user = User::factory()->create();

    expect($user->can('defined-elsewhere'))->toBeTrue()
        ->and($user->can('undefined'))->toBeFalse();
});

it('skips the bridge for guests', function (): void {
    expect(Gate::allows('edit-posts'))->toBeFalse();
});
