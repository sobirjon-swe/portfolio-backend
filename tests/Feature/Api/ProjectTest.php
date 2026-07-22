<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_publicly_with_technologies(): void
    {
        $project = Project::factory()->create();
        $project->technologies()->attach(Technology::factory()->count(2)->create());

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.technologies')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'is_featured', 'technologies' => [['id', 'name']]]],
            ]);
    }

    public function test_it_shows_a_single_project(): void
    {
        $project = Project::factory()->create(['title' => 'Portfolio Site']);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Portfolio Site');
    }

    public function test_it_returns_404_for_missing_project(): void
    {
        $this->getJson('/api/v1/projects/999')->assertNotFound();
    }

    public function test_guests_cannot_create_projects(): void
    {
        $this->postJson('/api/v1/projects', ['title' => 'X', 'description' => 'Y'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_authenticated_user_can_create_a_project_and_owns_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $techs = Technology::factory()->count(2)->create();

        $response = $this->postJson('/api/v1/projects', [
            'title' => 'My Cool Project',
            'description' => 'A description.',
            'is_featured' => true,
            'technology_ids' => $techs->pluck('id')->all(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'my-cool-project')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonCount(2, 'data.technologies')
            ->assertJsonPath('message', 'Project created.');

        $this->assertDatabaseHas('projects', [
            'title' => 'My Cool Project',
            'slug' => 'my-cool-project',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseCount('project_technology', 2);
    }

    public function test_slug_is_made_unique_on_collision(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Project::factory()->create(['slug' => 'duplicate-title']);

        $this->postJson('/api/v1/projects', [
            'title' => 'Duplicate Title',
            'description' => 'x',
        ])->assertCreated()->assertJsonPath('data.slug', 'duplicate-title-2');
    }

    public function test_it_validates_required_fields_and_technology_existence(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/projects', [
            'description' => 'no title',
            'technology_ids' => [9999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'technology_ids.0']);
    }

    public function test_updating_title_regenerates_slug_and_syncs_technologies(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create(['title' => 'Old', 'slug' => 'old']);
        $project->technologies()->attach(Technology::factory()->count(2)->create());
        $newTech = Technology::factory()->create();

        $this->patchJson("/api/v1/projects/{$project->id}", [
            'title' => 'Brand New',
            'technology_ids' => [$newTech->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'brand-new')
            ->assertJsonCount(1, 'data.technologies');

        $this->assertDatabaseCount('project_technology', 1);
    }

    public function test_authenticated_user_can_delete_a_project(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->deleteJson("/api/v1/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
