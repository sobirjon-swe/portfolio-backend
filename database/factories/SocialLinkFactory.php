<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    protected $model = SocialLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->unique()->randomElement(['github', 'linkedin', 'twitter', 'telegram', 'instagram']);

        return [
            'platform' => $platform,
            'url' => 'https://'.$platform.'.com/'.fake()->userName(),
            'icon' => $platform,
        ];
    }
}
