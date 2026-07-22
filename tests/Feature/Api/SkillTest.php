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

    public function test_it_lists_skills_publicly_ordered_by_proficiency(): void
    {
        Skill::factory()->create(['name' => 'Low', 'proficiency' => 30]);
        Skill::factory()->create(['name' => 'High', 'proficiency' => 90]);

        $this->getJson('/api/v1/skills')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'High'); // highest first
    }

    public function test_guests_cannot_create_skills(): void
    {
        $this->postJson('/api/v1/skills', ['name' => 'PHP', 'proficiency' => 80])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_a_skill(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/skills', ['name' => 'PHP', 'proficiency' => 85, 'category' => 'backend'])
            ->assertCreated()
            ->assertJsonPath('data.proficiency', 85);

        $this->assertDatabaseHas('skills', ['name' => 'PHP', 'proficiency' => 85]);
    }

    public function test_proficiency_must_be_between_0_and_100(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/skills', ['name' => 'PHP', 'proficiency' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('proficiency');
    }

    public function test_authenticated_user_can_delete_a_skill(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $skill = Skill::factory()->create();

        $this->deleteJson("/api/v1/skills/{$skill->id}")->assertNoContent();
        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }
}
