<?php

declare(strict_types=1);

use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Support\Models;
use Workbench\App\Models\User;

beforeEach(function (): void {
    config()->set('auth.providers.users.model', User::class);
});

it('grants the superuser role with every ability', function (): void {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('valkyrie:grant-superuser', ['email' => 'admin@example.com'])
        ->expectsOutputToContain('holds the system-admin role')
        ->assertSuccessful();

    expect($user->isA('system-admin'))->toBeTrue()
        ->and(Valkyrie::can($user, 'absolutely-anything'))->toBeTrue();
});

it('uses the configured superuser role name', function (): void {
    config()->set('valkyrie.superuser.role', 'owner');
    config()->set('valkyrie.superuser.title', 'Owner');
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('valkyrie:grant-superuser', ['email' => 'admin@example.com'])->assertSuccessful();

    expect($user->isA('owner'))->toBeTrue()
        ->and(Models::role()::query()->value('title'))->toBe('Owner');
});

it('fails for an unknown email', function (): void {
    $this->artisan('valkyrie:grant-superuser', ['email' => 'missing@example.com'])
        ->expectsOutputToContain('No user with email')
        ->assertFailed();
});

it('is idempotent', function (): void {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('valkyrie:grant-superuser', ['email' => 'admin@example.com'])->assertSuccessful();
    $this->artisan('valkyrie:grant-superuser', ['email' => 'admin@example.com'])->assertSuccessful();

    expect(Models::role()::query()->where('name', 'system-admin')->count())->toBe(1)
        ->and(Models::assignedRole()::query()->count())->toBe(1)
        ->and(Models::permission()::query()->count())->toBe(1);
});
