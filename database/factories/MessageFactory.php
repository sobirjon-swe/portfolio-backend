<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'budget' => fake()->randomElement(['$1k–3k', '$3k–5k', null]),
            'body' => fake()->paragraph(),
            'is_read' => false,
        ];
    }
}
