<?php

declare(strict_types=1);

use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Support\Models;
use Workbench\App\Models\Office;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('finds or creates an ability once', function (): void {
    $first = Valkyrie::ability('edit-posts', title: 'Edit posts');
    $second = Valkyrie::ability('edit-posts');

    expect($second->is($first))->toBeTrue()
        ->and($first->title)->toBe('Edit posts')
        ->and(Models::ability()::query()->count())->toBe(1);
});

it('separates abilities by entity and only_owned', function (): void {
    Valkyrie::ability('edit');
    Valkyrie::ability('edit', Post::class);
    Valkyrie::ability('edit', Post::class, onlyOwned: true);

    expect(Models::ability()::query()->count())->toBe(3);
});

it('finds or creates a role once', function (): void {
    $first = Valkyrie::role('editor', 'Editor');
    $second = Valkyrie::role('editor');

    expect($second->is($first))->toBeTrue()
        ->and($first->title)->toBe('Editor')
        ->and($first->isScoped())->toBeFalse();
});

it('creates scoped role definitions apart from global ones', function (): void {
    $office = Office::query()->create(['name' => 'HQ']);
    $global = Valkyrie::role('editor');
    $scoped = Valkyrie::role('editor', scope: $office);

    expect($scoped->is($global))->toBeFalse()
        ->and($scoped->isScoped())->toBeTrue()
        ->and($scoped->scope->is($office))->toBeTrue();
});

it('assigns and retracts for multiple actors at once', function (): void {
    [$a, $b] = User::factory()->count(2)->create();

    Valkyrie::assign('editor', [$a, $b]);
    expect($a->isA('editor'))->toBeTrue()
        ->and($b->isA('editor'))->toBeTrue();

    Valkyrie::retract('editor', [$a, $b]);
    expect($a->isNotA('editor'))->toBeTrue()
        ->and($b->isNotA('editor'))->toBeTrue();
});

it('reports holdsRole globally and per scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $user->assign('editor', $office);

    expect(Valkyrie::holdsRole($user, 'editor'))->toBeTrue()
        ->and(Valkyrie::holdsRole($user, 'editor', $office))->toBeTrue()
        ->and(Valkyrie::holdsRole($user, 'other'))->toBeFalse();
});

it('retracting an unknown role is a no-op', function (): void {
    $user = User::factory()->create();

    Valkyrie::retract('missing', $user);

    expect(Models::assignedRole()::query()->count())->toBe(0);
});
