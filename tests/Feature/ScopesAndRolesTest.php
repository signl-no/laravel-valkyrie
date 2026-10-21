<?php

declare(strict_types=1);

use Signl\Valkyrie\Exceptions\AmbiguousRoleException;
use Signl\Valkyrie\Exceptions\ScopeMismatchException;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Models\Role;
use Workbench\App\Models\Office;
use Workbench\App\Models\Report;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

it('keeps scoped role assignments out of other scopes', function (): void {
    $user = User::factory()->create();
    $hq = Office::query()->create(['name' => 'HQ']);
    $branch = Office::query()->create(['name' => 'Branch']);

    $role = Valkyrie::role('manager');
    Valkyrie::allow($role, $hq)->to('approve');
    $user->assign('manager', $hq);

    expect(Valkyrie::can($user, 'approve', on: $hq))->toBeTrue()
        ->and(Valkyrie::check($user, 'approve', on: $branch))->toBeNull()
        ->and(Valkyrie::check($user, 'approve'))->toBeNull();
});

it('never inherits a parent scope grant', function (): void {
    $user = User::factory()->create();
    $parent = Office::query()->create(['name' => 'Parent']);
    $child = Office::query()->create(['name' => 'Child']);

    Valkyrie::allow($user, $parent)->to('approve');

    expect(Valkyrie::check($user, 'approve', on: $child))->toBeNull();
});

it('applies global grants inside every scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $user->allow()->to('approve');

    expect(Valkyrie::can($user, 'approve', on: $office))->toBeTrue();
});

it('uses the entity accessScope over the passed scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $report = Report::query()->create(['title' => 'Q1', 'office_id' => $office->getKey()]);

    Valkyrie::allow($user, $office)->to('view', Report::class);

    expect(Valkyrie::can($user, 'view', $report))->toBeTrue();
});

it('forces a scoped role definition onto its scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    Valkyrie::role('office-admin', scope: $office);

    $user->assign('office-admin');

    expect($user->isA('office-admin', $office))->toBeTrue();
});

it('rejects assigning a scoped definition on a different scope', function (): void {
    $office = Office::query()->create(['name' => 'HQ']);
    $other = Office::query()->create(['name' => 'Other']);
    $role = Valkyrie::role('office-admin', scope: $office);

    Valkyrie::assign($role, User::factory()->create(), $other);
})->throws(ScopeMismatchException::class);

it('rejects a role name that only exists for other scopes', function (): void {
    $office = Office::query()->create(['name' => 'HQ']);
    $other = Office::query()->create(['name' => 'Other']);
    Valkyrie::role('office-admin', scope: $office);
    Valkyrie::role('office-admin', scope: $other);

    Valkyrie::assign('office-admin', User::factory()->create(), Office::query()->create(['name' => 'Third']));
})->throws(ScopeMismatchException::class);

it('rejects an ambiguous role name without a scope', function (): void {
    Valkyrie::role('office-admin', scope: Office::query()->create(['name' => 'HQ']));
    Valkyrie::role('office-admin', scope: Office::query()->create(['name' => 'Other']));

    Valkyrie::assign('office-admin', User::factory()->create());
})->throws(AmbiguousRoleException::class);

it('resolves a single scoped definition without a scope', function (): void {
    $office = Office::query()->create(['name' => 'HQ']);
    Valkyrie::role('office-admin', scope: $office);
    $user = User::factory()->create();

    $user->assign('office-admin');

    expect($user->isA('office-admin', $office))->toBeTrue();
});

it('checks globally when the entity has no access scope, ignoring the passed scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $report = Report::query()->create(['title' => 'Unfiled']);

    Valkyrie::allow($user, $office)->to('view', Report::class);

    expect(Valkyrie::can($user, 'view', $report, on: $office))->toBeFalse();
});

it('applies the entity access scope in whereCan', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $report = Report::query()->create(['title' => 'Q1', 'office_id' => $office->getKey()]);

    $user->allow()->to('view', Report::class);
    $user->forbid($office)->to('view', Report::class);

    expect($user->can('view', $report))->toBeFalse()
        ->and(User::query()->whereCan('view', $report)->count())->toBe(0);
});

it('removes grants, assignments and role definitions scoped to a deleted scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    Valkyrie::allow($user, $office)->to('approve');
    $user->assign('manager', $office);
    $lead = Valkyrie::role('lead', scope: $office);
    Valkyrie::allow($lead)->to('sign');
    $user->assign('lead');
    expect(Valkyrie::can($user, 'approve', on: $office))->toBeTrue();

    $office->delete();

    expect(Permission::query()->count())->toBe(0)
        ->and(AssignedRole::query()->count())->toBe(0)
        ->and(Role::query()->where('name', 'lead')->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'manager')->exists())->toBeTrue()
        ->and(Valkyrie::check($user, 'approve', on: $office))->toBeNull();
});

it('keeps scoped rows on soft delete and removes them on force delete', function (): void {
    $user = User::factory()->create();
    $team = Team::query()->create(['name' => 'Core']);
    Valkyrie::allow($user, $team)->to('deploy');

    $team->delete();
    expect(Permission::query()->count())->toBe(1);

    $team->forceDelete();
    expect(Permission::query()->count())->toBe(0);
});
