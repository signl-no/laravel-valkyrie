<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Models\AssignedRole;
use Signl\Valkyrie\Models\Role;

/**
 * @extends Factory<AssignedRole>
 */
final class AssignedRoleFactory extends Factory
{
    protected $model = AssignedRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
        ];
    }

    public function heldBy(Model $actor): static
    {
        return $this->state([
            'entity_type' => $actor->getMorphClass(),
            'entity_id' => $actor->getKey(),
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
