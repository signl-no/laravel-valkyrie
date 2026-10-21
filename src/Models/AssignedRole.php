<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Signl\Valkyrie\Database\Factories\AssignedRoleFactory;
use Signl\Valkyrie\Support\Models;

/**
 * A role held by an actor, optionally inside one scope.
 *
 * @property int $id
 * @property int $role_id
 * @property string $entity_type
 * @property int|string $entity_id
 * @property string|null $scope_type
 * @property int|string|null $scope_id
 */
#[Fillable('role_id', 'entity_type', 'entity_id', 'scope_type', 'scope_id')]
#[UseFactory(AssignedRoleFactory::class)]
#[WithoutTimestamps]
class AssignedRole extends Model
{
    /** @use HasFactory<AssignedRoleFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity');
    }

    public function getTable(): string
    {
        return config()->string('valkyrie.tables.assigned_roles');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Models::role(), 'role_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }
}
