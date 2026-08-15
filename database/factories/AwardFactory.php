<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Award;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => ['en' => $this->faker->sentence(3)],
            'issuer' => $this->faker->company(),
            'type' => 'certificate',
            'description' => ['en' => $this->faker->sentence()],
            'issued_on' => (string) $this->faker->numberBetween(2019, 2026),
            'credential_id' => $this->faker->bothify('??-####'),
            'credential_url' => $this->faker->url(),
            'sort_order' => 0,
        ];
    }

    public function award(): self
    {
        return $this->state(fn (): array => ['type' => 'award']);
    }
}
