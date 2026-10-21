<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Permission;

/**
 * @extends Factory<Permission>
 */
final class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ability_id' => Ability::factory(),
            'forbidden' => false,
        ];
    }

    /**
     * @param  list<string>  $names
     */
    public function except(array $names): static
    {
        return $this->state(['except' => $names]);
    }

    public function forbidden(): static
    {
        return $this->state(['forbidden' => true]);
    }

    public function grantedTo(Model $entity): static
    {
        return $this->state([
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
        ]);
    }

    public function scopedTo(Model $scope): static
    {
        return $this->state([
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
        ]);
    }
}
