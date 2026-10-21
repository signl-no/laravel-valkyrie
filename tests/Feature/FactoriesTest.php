<?php

declare(strict_types=1);

use Signl\Valkyrie\Database\Factories\AbilityFactory;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Models\Role;
use Workbench\App\Models\Office;
use Workbench\App\Models\User;

it('resolves factories through the UseFactory attribute', function (): void {
    expect(Ability::factory())->toBeInstanceOf(AbilityFactory::class);
});

it('creates abilities', function (): void {
    $ability = Ability::factory()->create();

    expect($ability->exists)->toBeTrue()
        ->and($ability->only_owned)->toBeFalse();
});

it('creates roles with a scope state', function (): void {
    $office = Office::query()->create(['name' => 'HQ']);
    $role = Role::factory()->scopedTo($office)->create();

    expect($role->isScoped())->toBeTrue()
        ->and($role->scope->is($office))->toBeTrue();
});

it('creates permissions with states', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);

    $permission = Permission::factory()
        ->grantedTo($user)
        ->scopedTo($office)
        ->forbidden()
        ->except(['delete'])
        ->create();

    expect($permission->forbidden)->toBeTrue()
        ->and($permission->except)->toBe(['delete'])
        ->and($permission->entity_id)->toBe($user->getKey())
        ->and($permission->scope_id)->toBe($office->getKey())
        ->and($permission->ability->exists)->toBeTrue();
});

it('creates assigned roles with states', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);

    $assignment = AssignedRole::factory()->heldBy($user)->scopedTo($office)->create();

    expect($assignment->role->exists)->toBeTrue()
        ->and($assignment->entity_id)->toBe($user->getKey())
        ->and($assignment->scope_id)->toBe($office->getKey())
        ->and($user->isA($assignment->role->name, $office))->toBeTrue();
});
