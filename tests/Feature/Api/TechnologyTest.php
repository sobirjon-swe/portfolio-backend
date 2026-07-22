<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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
}
