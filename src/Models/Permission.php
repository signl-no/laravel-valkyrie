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
use Signl\Valkyrie\Database\Factories\PermissionFactory;
use Signl\Valkyrie\Support\Models;

/**
 * An allow or forbid of one ability for one entity (actor or role); forbid wins.
 *
 * @property int $id
 * @property int $ability_id
 * @property string $entity_type
 * @property int|string $entity_id
 * @property bool $forbidden
 * @property list<string>|null $except
 * @property string|null $scope_type
 * @property int|string|null $scope_id
 */
#[Fillable('ability_id', 'entity_type', 'entity_id', 'forbidden', 'except', 'scope_type', 'scope_id')]
#[UseFactory(PermissionFactory::class)]
#[WithoutTimestamps]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ability, $this>
     */
    public function ability(): BelongsTo
    {
        return $this->belongsTo(Models::ability(), 'ability_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity');
    }

    public function getTable(): string
    {
        return config()->string('valkyrie.tables.permissions');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo('scope');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'forbidden' => 'boolean',
            'except' => 'array',
        ];
    }
}
