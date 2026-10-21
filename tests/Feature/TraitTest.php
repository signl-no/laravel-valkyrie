<?php

declare(strict_types=1);

use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Permission;
use Signl\Valkyrie\Models\Role;
use Workbench\App\Models\Member;
use Workbench\App\Models\Office;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('exposes granted and forbidden abilities through relations', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->forbid()->to('delete-posts');

    expect($user->getAbilities()->pluck('name')->all())->toBe(['edit-posts'])
        ->and($user->getForbiddenAbilities()->pluck('name')->all())->toBe(['delete-posts'])
        ->and($user->abilities()->get())->each->toBeInstanceOf(Ability::class);
});

it('exposes held roles through the roles relation', function (): void {
    $user = User::factory()->create();
    $user->assign('editor');

    expect($user->roles->pluck('name')->all())->toBe(['editor'])
        ->and($user->roles->first())->toBeInstanceOf(Role::class);
});

it('answers isA, isAn, isNotA and isAll', function (): void {
    $user = User::factory()->create();
    $user->assign('editor')->assign('author');

    expect($user->isA('editor'))->toBeTrue()
        ->and($user->isAn('author'))->toBeTrue()
        ->and($user->isNotA('admin'))->toBeTrue()
        ->and($user->isAll('editor', 'author'))->toBeTrue()
        ->and($user->isAll('editor', 'admin'))->toBeFalse();
});

it('answers isA per scope', function (): void {
    $user = User::factory()->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $other = Office::query()->create(['name' => 'Other']);
    $user->assign('manager', $office);

    expect($user->isA('manager'))->toBeTrue()
        ->and($user->isA('manager', $office))->toBeTrue()
        ->and($user->isA('manager', $other))->toBeFalse();
});

it('finds actors with whereCan', function (): void {
    [$granted, $other] = User::factory()->count(2)->create();
    $granted->allow()->to('edit-posts');

    expect(User::query()->whereCan('edit-posts')->pluck('id')->all())->toBe([$granted->getKey()]);
});

it('finds actors granted through roles with whereCan', function (): void {
    [$granted, $other] = User::factory()->count(2)->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $granted->assign('editor');

    expect(User::query()->whereCan('edit-posts')->pluck('id')->all())->toBe([$granted->getKey()]);
});

it('excludes forbidden actors from whereCan', function (): void {
    [$granted, $forbidden] = User::factory()->count(2)->create();
    $granted->allow()->to('edit-posts');
    $forbidden->allow()->to('edit-posts');
    $forbidden->forbid()->to('edit-posts');

    expect(User::query()->whereCan('edit-posts')->pluck('id')->all())->toBe([$granted->getKey()]);
});

it('matches wildcard grants and except lists in whereCan', function (): void {
    [$all, $excepted] = User::factory()->count(2)->create();
    $all->allow()->everything();
    $excepted->allow()->everything(except: ['edit-posts']);

    expect(User::query()->whereCan('edit-posts')->pluck('id')->all())->toBe([$all->getKey()]);
});

it('excludes owner-only grants from whereCan', function (): void {
    $user = User::factory()->create();
    $user->allow()->toOwn(Post::class);

    expect(User::query()->whereCan('edit', Post::class)->count())->toBe(0);
});

it('finds actors holding a role with whereIs', function (): void {
    [$editor, $other] = User::factory()->count(2)->create();
    $editor->assign('editor');

    expect(User::query()->whereIs('editor')->pluck('id')->all())->toBe([$editor->getKey()]);
});

it('filters whereIs by scope', function (): void {
    [$scoped, $other] = User::factory()->count(2)->create();
    $office = Office::query()->create(['name' => 'HQ']);
    $scoped->assign('manager', $office);
    $other->assign('manager', Office::query()->create(['name' => 'Other']));

    expect(User::query()->whereIs('manager', $office)->pluck('id')->all())->toBe([$scoped->getKey()]);
});

it('removes grants and role assignments when the actor is deleted', function (): void {
    $user = User::factory()->create();
    $user->allow()->to('edit-posts');
    $user->assign('editor');
    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();

    $user->delete();

    expect(Permission::query()->count())->toBe(0)
        ->and(AssignedRole::query()->count())->toBe(0)
        ->and(Valkyrie::check($user, 'edit-posts'))->toBeNull();
});

it('keeps a soft-deleted actor\'s rows until it is force deleted', function (): void {
    $member = Member::query()->create(['name' => 'Ada']);
    $member->allow()->to('edit-posts');
    $member->assign('editor');

    $member->delete();
    expect(Permission::query()->count())->toBe(1)
        ->and(AssignedRole::query()->count())->toBe(1);

    $member->forceDelete();
    expect(Permission::query()->count())->toBe(0)
        ->and(AssignedRole::query()->count())->toBe(0);
});
