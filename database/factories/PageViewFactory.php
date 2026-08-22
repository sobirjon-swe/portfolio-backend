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
            'referrer' => fake()->randomElement([null, 'Google', 'LinkedIn', 'Telegram']),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'device' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
            'platform' => fake()->randomElement(['Windows', 'macOS', 'Android', 'iOS', 'Linux']),
            'is_bot' => false,
        ];
    }

    /**
     * A crawler rather than a person. Every visitor figure filters these out,
     * so tests need a way to prove that the filtering actually happens.
     */
    public function bot(): static
    {
        return $this->state(fn (): array => [
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'device' => null,
            'browser' => null,
            'platform' => null,
            'is_bot' => true,
        ]);
    }

    /**
     * Pin a view to a moment, for trend and window assertions.
     */
    public function at(\DateTimeInterface $moment): static
    {
        return $this->state(fn (): array => ['created_at' => $moment]);
    }

    /**
     * Pin a view to one visitor, so distinct-visitor counts can be asserted
     * separately from raw view counts.
     */
    public function fromVisitor(string $identity): static
    {
        return $this->state(fn (): array => ['ip_hash' => hash('sha256', $identity)]);
    }
}
