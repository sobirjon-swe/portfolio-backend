<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imageable_type' => Project::class,
            'imageable_id' => Project::factory(),
            'url' => fake()->imageUrl(),
            'path' => null,
            'alt' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }

    /**
     * An image backed by a file on the configured disk.
     */
    public function uploaded(string $path = 'uploads/projects/1/example.jpg'): self
    {
        return $this->state(fn (): array => ['path' => $path, 'url' => null]);
    }
}
