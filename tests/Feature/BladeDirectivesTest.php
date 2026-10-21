<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Workbench\App\Models\User;

it('renders the role branch for a user holding the role', function (): void {
    $user = User::factory()->create();
    $user->assign('editor');
    $this->actingAs($user);

    expect(trim(Blade::render("@role('editor') yes @else no @endrole")))->toBe('yes');
});

it('renders the else branch for a user without the role', function (): void {
    $this->actingAs(User::factory()->create());

    expect(trim(Blade::render("@role('editor') yes @else no @endrole")))->toBe('no');
});

it('renders the else branch for guests', function (): void {
    expect(trim(Blade::render("@role('editor') yes @else no @endrole")))->toBe('no');
});

it('supports unlessrole', function (): void {
    $this->actingAs(User::factory()->create());

    expect(trim(Blade::render("@unlessrole('editor') missing @endrole")))->toBe('missing');
});
