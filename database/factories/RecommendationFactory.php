<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_role' => fake()->jobTitle(),
            'author_company' => fake()->company(),
            'relationship' => fake()->randomElement(Recommendation::RELATIONSHIPS),
            'body' => fake()->paragraph(4),
            'linkedin_url' => 'https://www.linkedin.com/in/'.fake()->unique()->userName(),
            'is_approved' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['is_approved' => false]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['is_approved' => true]);
    }
}
