<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Signl\Valkyrie\Facades\Valkyrie;
use Workbench\App\Models\Office;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Route::get('/needs-role', fn (): string => 'role-ok')->middleware('role:admin,editor');
    Route::get('/needs-role-any-scope', fn (): string => 'role-ok')->middleware('role:admin,any');
    Route::get('/needs-ability', fn (): string => 'ability-ok')->middleware('ability:edit-posts,view-posts');
});

it('lets a user with one of the roles through', function (): void {
    $user = User::factory()->create();
    $user->assign('editor');

    $this->actingAs($user)->get('/needs-role')->assertOk()->assertSee('role-ok');
});

it('denies a user without the roles with a 403', function (): void {
    $this->actingAs(User::factory()->create())->get('/needs-role')->assertForbidden();
});

it('throws AuthorizationException for a user without the roles', function (): void {
    $this->withoutExceptionHandling();

    $this->actingAs(User::factory()->create())->get('/needs-role');
})->throws(AuthorizationException::class);

it('throws AuthenticationException for guests', function (): void {
    $this->withoutExceptionHandling();

    $this->get('/needs-role');
})->throws(AuthenticationException::class);

it('lets a user with one of the abilities through', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('view-posts');

    $this->actingAs($user)->get('/needs-ability')->assertOk()->assertSee('ability-ok');
});

it('denies a user without the abilities', function (): void {
    $this->actingAs(User::factory()->create())->get('/needs-ability')->assertForbidden();
});

it('lets a forbid override an allow', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->forbid()->to('edit-posts');

    $this->actingAs($user)->get('/needs-ability')->assertForbidden();
});

it('throws AuthenticationException for guests on the ability middleware', function (): void {
    $this->withoutExceptionHandling();

    $this->get('/needs-ability');
})->throws(AuthenticationException::class);

it('honors abilities granted through roles', function (): void {
    $user = User::factory()->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $user->assign('editor');

    $this->actingAs($user)->get('/needs-ability')->assertOk();
});

it('rejects a role held only inside a scope', function (): void {
    $user = User::factory()->create();
    $user->assign('admin', Office::query()->create(['name' => 'HQ']));

    $this->actingAs($user)->get('/needs-role')->assertForbidden();
});

it('accepts a role held inside a scope with the any flag', function (): void {
    $user = User::factory()->create();
    $user->assign('admin', Office::query()->create(['name' => 'HQ']));

    $this->actingAs($user)->get('/needs-role-any-scope')->assertOk();
});

it('accepts a global role with the any flag', function (): void {
    $user = User::factory()->create();
    $user->assign('admin');

    $this->actingAs($user)->get('/needs-role-any-scope')->assertOk();
});
