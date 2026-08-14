<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_skills_publicly_grouped_by_category(): void
    {
        Skill::factory()->create(['name' => 'Caching strategy', 'category' => 'backend']);
        Skill::factory()->create(['name' => 'Query optimization', 'category' => 'data']);

        $this->getJson('/api/v1/skills')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Alphabetical by category keeps the order stable between requests.
            ->assertJsonPath('data.0.category', 'backend');
    }

    public function test_guests_cannot_create_skills(): void
    {
        $this->postJson('/api/v1/skills', ['name' => 'REST API design'])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_a_skill(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/skills', ['name' => 'REST API design', 'category' => 'backend'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'REST API design');

        $this->assertDatabaseHas('skills', ['name' => 'REST API design', 'category' => 'backend']);
    }

    /**
     * A skill is something I can do, not a number out of a hundred — the
     * percentage was dropped rather than moved, so sending one is simply
     * ignored instead of quietly stored.
     */
    public function test_a_percentage_is_no_longer_accepted_or_stored(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/skills', ['name' => 'Testing', 'proficiency' => 150])
            ->assertCreated()
            ->assertJsonMissingPath('data.proficiency');
    }

    public function test_authenticated_user_can_delete_a_skill(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $skill = Skill::factory()->create();

        $this->deleteJson("/api/v1/skills/{$skill->id}")->assertNoContent();
        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }
}
