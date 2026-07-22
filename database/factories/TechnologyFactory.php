<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technology>
 */
class TechnologyFactory extends Factory
{
    protected $model = Technology::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'icon' => 'icon-'.fake()->word(),
            'category' => fake()->randomElement(['backend', 'frontend', 'database']),
        ];
    }
}
