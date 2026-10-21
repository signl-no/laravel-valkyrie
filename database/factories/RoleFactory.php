<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\Models\Role;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'title' => fake()->jobTitle(),
        ];
    }

    public function scopedTo(Model $scope): static
    {
        return $this->state([
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
        ]);
    }
}
