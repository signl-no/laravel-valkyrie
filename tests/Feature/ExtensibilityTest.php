<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Signl\Valkyrie\Facades\Valkyrie;
use Signl\Valkyrie\Support\Models;
use Workbench\App\Models\CustomAbility;
use Workbench\App\Models\CustomRole;
use Workbench\App\Models\User;
use Workbench\App\Observers\CustomRoleObserver;

beforeEach(function (): void {
    config()->set('valkyrie.models.role', CustomRole::class);
    config()->set('valkyrie.models.ability', CustomAbility::class);
    Relation::morphMap([
        config()->string('valkyrie.morphs.role') => Models::role(),
        config()->string('valkyrie.morphs.ability') => Models::ability(),
    ]);
    CustomRoleObserver::reset();
});

it('creates configured subclasses through the manager', function (): void {
    $role = Valkyrie::role('system-editor');
    $ability = Valkyrie::ability('edit-posts');

    expect($role)->toBeInstanceOf(CustomRole::class)
        ->and($role->isSystemRole())->toBeTrue()
        ->and($ability)->toBeInstanceOf(CustomAbility::class);
});

it('returns subclass instances from the trait relations', function (): void {
    $user = User::factory()->create();
    $user->assign('editor');
    $user->allow()->to('edit-posts');

    expect($user->roles->first())->toBeInstanceOf(CustomRole::class)
        ->and($user->getAbilities()->first())->toBeInstanceOf(CustomAbility::class);
});

it('resolves grants through subclass roles', function (): void {
    $user = User::factory()->create();
    $role = Valkyrie::role('editor');
    Valkyrie::allow($role)->to('edit-posts');
    $user->assign('editor');

    expect(Valkyrie::can($user, 'edit-posts'))->toBeTrue();
});

it('inherits the fillable attributes on subclasses', function (): void {
    $role = CustomRole::query()->create(['name' => 'filled', 'title' => 'Filled']);

    expect($role->name)->toBe('filled')
        ->and(CustomRole::query()->where('name', 'filled')->exists())->toBeTrue();
});

it('fires subclass-level observers alongside inherited behavior', function (): void {
    $role = Valkyrie::role('audited');
    $role->delete();

    expect(CustomRoleObserver::$events)->toBe(['created:audited', 'deleted:audited']);
});

it('rejects a configured class that does not extend the base model', function (): void {
    config()->set('valkyrie.models.role', stdClass::class);

    Models::role();
})->throws(InvalidArgumentException::class, 'must name a class extending');
