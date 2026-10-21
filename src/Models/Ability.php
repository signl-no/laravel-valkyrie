<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Signl\Valkyrie\Database\Factories\AbilityFactory;
use Signl\Valkyrie\Support\Models;

/**
 * Something an actor can be allowed or forbidden to do.
 *
 * @property int $id
 * @property string $name
 * @property string|null $title
 * @property string|null $entity_type
 * @property int|string|null $entity_id
 * @property bool $only_owned
 * @property array<string, mixed>|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable('name', 'title', 'entity_type', 'entity_id', 'only_owned', 'options')]
#[UseFactory(AbilityFactory::class)]
class Ability extends Model
{
    /** @use HasFactory<AbilityFactory> */
    use HasFactory;

    public function getTable(): string
    {
        return config()->string('valkyrie.tables.abilities');
    }

    /**
     * @return HasMany<Permission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Models::permission(), 'ability_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'only_owned' => 'boolean',
            'options' => 'array',
        ];
    }
}
