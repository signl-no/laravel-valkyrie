<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Signl\Valkyrie\Models\Ability;

/**
 * @extends Factory<Ability>
 */
final class AbilityFactory extends Factory
{
    protected $model = Ability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'only_owned' => false,
        ];
    }
}
