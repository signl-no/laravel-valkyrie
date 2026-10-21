<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Workbench\App\Models\Office;
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

it('ignores a role held only inside a scope by default', function (): void {
    $user = User::factory()->create();
    $user->assign('editor', Office::query()->create(['name' => 'HQ']));
    $this->actingAs($user);

    expect(trim(Blade::render("@role('editor') yes @else no @endrole")))->toBe('no')
        ->and(trim(Blade::render("@unlessrole('editor') missing @endrole")))->toBe('missing');
});

it('checks inside the given scope', function (): void {
    $user = User::factory()->create();
    $hq = Office::query()->create(['name' => 'HQ']);
    $branch = Office::query()->create(['name' => 'Branch']);
    $user->assign('editor', $hq);
    $this->actingAs($user);

    $view = "@role('editor', \$office) yes @else no @endrole";

    expect(trim(Blade::render($view, ['office' => $hq])))->toBe('yes')
        ->and(trim(Blade::render($view, ['office' => $branch])))->toBe('no');
});

it('accepts a role held in any scope with any', function (): void {
    $user = User::factory()->create();
    $user->assign('editor', Office::query()->create(['name' => 'HQ']));
    $this->actingAs($user);

    expect(trim(Blade::render("@role('editor', 'any') yes @else no @endrole")))->toBe('yes');
});

it('counts a global role inside any given scope', function (): void {
    $user = User::factory()->create();
    $user->assign('editor');
    $this->actingAs($user);

    expect(trim(Blade::render("@role('editor', \$office) yes @else no @endrole", ['office' => Office::query()->create(['name' => 'HQ'])])))->toBe('yes');
});
