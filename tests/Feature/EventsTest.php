<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Signl\Valkyrie\Events\AbilityForbidden;
use Signl\Valkyrie\Events\AbilityGranted;
use Signl\Valkyrie\Events\AbilityRevoked;
use Signl\Valkyrie\Events\RoleAssigned;
use Signl\Valkyrie\Events\RoleRetracted;
use Signl\Valkyrie\Facades\Valkyrie;
use Workbench\App\Models\Office;
use Workbench\App\Models\User;

it('dispatches AbilityGranted on allow', function (): void {
    Event::fake([AbilityGranted::class]);
    $user = User::factory()->create();

    $user->allow()->to('edit-posts');

    Event::assertDispatched(AbilityGranted::class, fn (AbilityGranted $event): bool => $event->actor->is($user)
        && $event->ability->name === 'edit-posts'
        && $event->scope === null);
});

it('dispatches AbilityForbidden on forbid', function (): void {
    Event::fake([AbilityForbidden::class]);
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);

    Valkyrie::forbid($user, $office)->to('edit-posts');

    Event::assertDispatched(AbilityForbidden::class, fn (AbilityForbidden $event): bool => $event->actor->is($user)
        && $event->scope?->is($office) === true);
});

it('dispatches AbilityRevoked on disallow and unforbid', function (): void {
    Event::fake([AbilityRevoked::class]);
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->forbid()->to('edit-posts');

    Valkyrie::disallow($user)->to('edit-posts');
    Valkyrie::unforbid($user)->to('edit-posts');

    Event::assertDispatched(AbilityRevoked::class, fn (AbilityRevoked $event): bool => ! $event->wasForbidden);
    Event::assertDispatched(AbilityRevoked::class, fn (AbilityRevoked $event): bool => $event->wasForbidden);
});

it('does not dispatch grant events for idempotent repeats', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');

    Event::fake([AbilityGranted::class]);
    $user->allow()->to('edit-posts');

    Event::assertNotDispatched(AbilityGranted::class);
});

it('dispatches RoleAssigned and RoleRetracted once each', function (): void {
    Event::fake([RoleAssigned::class, RoleRetracted::class]);
    $user = User::factory()->create();

    $user->assign('editor');
    $user->assign('editor');
    $user->retract('editor');
    $user->retract('editor');

    Event::assertDispatchedTimes(RoleAssigned::class, 1);
    Event::assertDispatchedTimes(RoleRetracted::class, 1);
});

it('defers event listeners to after the commit', function (): void {
    $user = User::factory()->create();
    $heard = [];
    Event::listen(AbilityGranted::class, function () use (&$heard): void {
        $heard[] = 'granted';
    });

    DB::transaction(function () use ($user, &$heard): void {
        $user->allow()->to('edit-posts');
        expect($heard)->toBe([]);
    });

    expect($heard)->toBe(['granted']);
});
