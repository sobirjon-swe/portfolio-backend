<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_an_empty_install(): void
    {
        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        $this->assertSame(3, Experience::query()->count());
        $this->assertSame(12, Technology::query()->count());
        $this->assertSame(10, Skill::query()->count());
        $this->assertSame(4, SocialLink::query()->count());
    }

    public function test_the_current_role_sorts_above_the_older_ones(): void
    {
        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        $first = Experience::query()->orderByDesc('sort_order')->first();

        $this->assertNull($first->end_date, 'The row with no end date should sort first.');
        $this->assertSame('Software Engineer', $first->getTranslation('role', 'en'));
    }

    public function test_every_role_is_stored_in_all_three_languages(): void
    {
        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        foreach (Experience::query()->get() as $experience) {
            foreach (['en', 'uz', 'ru'] as $locale) {
                $this->assertNotSame('', $experience->getTranslation('role', $locale));
                $this->assertNotSame('', $experience->getTranslation('description', $locale));
            }
        }
    }

    /**
     * It is meant to be safe on a live box, including a second run after new
     * entries are added to the command.
     */
    public function test_running_it_twice_creates_nothing_the_second_time(): void
    {
        $this->artisan('portfolio:seed-profile')->assertSuccessful();
        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        $this->assertSame(3, Experience::query()->count());
        $this->assertSame(12, Technology::query()->count());
        $this->assertSame(10, Skill::query()->count());
        $this->assertSame(4, SocialLink::query()->count());
    }

    public function test_it_leaves_an_edited_row_alone(): void
    {
        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        $technology = Technology::query()->where('name', 'PHP')->firstOrFail();
        $technology->update(['category' => 'my-own-grouping']);

        $this->artisan('portfolio:seed-profile')->assertSuccessful();

        $this->assertSame('my-own-grouping', $technology->refresh()->category);
        $this->assertSame(12, Technology::query()->count());
    }
}
