<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_name' => fake()->name(),
            'body' => fake()->paragraph(),
            // Comments start unapproved, as they do in the application.
            'is_approved' => false,
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => ['is_approved' => true]);
    }
}
