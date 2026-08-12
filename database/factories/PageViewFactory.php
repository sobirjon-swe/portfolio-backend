<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page' => fake()->randomElement(['/', '/blog', '/projects']),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
