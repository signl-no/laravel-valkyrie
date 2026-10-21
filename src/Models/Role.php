<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Signl\Valkyrie\Database\Factories\RoleFactory;
use Signl\Valkyrie\Support\Models;

/**
 * A named bundle of permissions; a scoped definition forces its scope on assignments.
 *
 * @property int $id
 * @property string $name
 * @property string|null $title
 * @property string|null $scope_type
 * @property int|string|null $scope_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $scope
 */
#[Fillable('name', 'title', 'scope_type', 'scope_id')]
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return MorphToMany<Ability, $this>
     */
    public function abilities(): MorphToMany
    {
        return $this
            ->morphToMany(Models::ability(), 'entity', config()->string('valkyrie.tables.permissions'), 'entity_id', 'ability_id')
            ->withPivot('forbidden', 'scope_type', 'scope_id');
    }

    /**
     * @return HasMany<AssignedRole, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Models::assignedRole(), 'role_id');
    }

    public function getTable(): string
    {
        return config()->string('valkyrie.tables.roles');
    }

    public function isScoped(): bool
    {
        return $this->scope_type !== null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }

    /**
     * Permissions point at roles by morph, not foreign key, so they are removed here.
     */
    protected static function booted(): void
    {
        self::deleted(function (Role $role): void {
            Models::permission()::query()
                ->where('entity_type', $role->getMorphClass())
                ->where('entity_id', $role->getKey())
                ->delete();
        });
    }
}
