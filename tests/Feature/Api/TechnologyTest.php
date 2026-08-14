<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Skill;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_technologies_publicly(): void
    {
        Technology::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/technologies');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'icon', 'category', 'created_at', 'updated_at']],
            ]);
    }

    public function test_it_shows_a_single_technology(): void
    {
        $technology = Technology::factory()->create(['name' => 'Laravel']);

        $this->getJson("/api/v1/technologies/{$technology->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Laravel');
    }

    public function test_it_returns_404_for_missing_technology(): void
    {
        $this->getJson('/api/v1/technologies/999')->assertNotFound();
    }

    public function test_guests_cannot_create_technologies(): void
    {
        $this->postJson('/api/v1/technologies', ['name' => 'PHP'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('technologies', 0);
    }

    public function test_authenticated_user_can_create_a_technology(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/technologies', [
            'name' => 'PostgreSQL',
            'category' => 'database',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'PostgreSQL')
            ->assertJsonPath('message', 'Technology created.');

        $this->assertDatabaseHas('technologies', [
            'name' => 'PostgreSQL',
            'category' => 'database',
        ]);
    }

    public function test_it_validates_required_name_on_create(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/technologies', ['category' => 'backend'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_authenticated_user_can_partially_update_a_technology(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $technology = Technology::factory()->create(['name' => 'Vue', 'category' => 'frontend']);

        $this->patchJson("/api/v1/technologies/{$technology->id}", ['name' => 'React'])
            ->assertOk()
            ->assertJsonPath('data.name', 'React')
            ->assertJsonPath('data.category', 'frontend'); // untouched

        $this->assertDatabaseHas('technologies', ['id' => $technology->id, 'name' => 'React']);
    }

    public function test_authenticated_user_can_delete_a_technology(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $technology = Technology::factory()->create();

        $this->deleteJson("/api/v1/technologies/{$technology->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('technologies', ['id' => $technology->id]);
    }

    public function test_a_technology_can_carry_a_proficiency(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/technologies', ['name' => 'Laravel', 'proficiency' => 85])
            ->assertCreated()
            ->assertJsonPath('data.proficiency', 85);

        $this->assertDatabaseHas('technologies', ['name' => 'Laravel', 'proficiency' => 85]);
    }

    /**
     * Unset is not the same as zero — the stack section draws a bar only for
     * the entries that actually carry a level.
     */
    public function test_proficiency_is_optional_and_stays_null_when_omitted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/technologies', ['name' => 'Nginx'])
            ->assertCreated()
            ->assertJsonPath('data.proficiency', null);
    }

    public function test_proficiency_must_be_between_0_and_100(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/technologies', ['name' => 'Redis', 'proficiency' => 140])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('proficiency');
    }

    public function test_the_listing_puts_the_rated_ones_first(): void
    {
        Technology::factory()->create(['name' => 'Unrated', 'proficiency' => null]);
        Technology::factory()->create(['name' => 'Weaker', 'proficiency' => 40]);
        Technology::factory()->create(['name' => 'Stronger', 'proficiency' => 90]);

        $this->getJson('/api/v1/technologies')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Stronger')
            ->assertJsonPath('data.1.name', 'Weaker')
            ->assertJsonPath('data.2.name', 'Unrated');
    }

    /**
     * Both screens have a picker, but they draw from different catalogs — the
     * logo grid is what filed "Notion" and "WordPress" as things I can do, so
     * an icon sent to the skills importer is dropped rather than stored.
     */
    public function test_the_skill_importer_never_stores_an_icon(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/skills/bulk', [
            'items' => [['name' => 'Query optimization', 'icon' => 'react', 'category' => 'data']],
        ])
            ->assertCreated()
            ->assertJsonMissingPath('data.0.icon');

        $this->assertDatabaseHas('skills', ['name' => 'Query optimization', 'category' => 'data']);
    }

    public function test_the_skill_importer_skips_names_that_already_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Skill::factory()->create(['name' => 'Automated testing']);

        $this->postJson('/api/v1/skills/bulk', [
            'items' => [
                ['name' => 'Automated testing', 'category' => 'quality'],
                ['name' => 'Code review', 'category' => 'quality'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->assertSame(2, Skill::query()->count());
    }
}
