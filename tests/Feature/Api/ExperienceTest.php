<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function make(array $overrides = []): Experience
    {
        return Experience::create(array_merge([
            'company' => 'Acme',
            'role' => ['en' => 'Engineer'],
            'description' => ['en' => 'Did things.'],
            'start_date' => '2023',
            'end_date' => null,
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_it_lists_experiences_publicly(): void
    {
        $this->make();

        $this->getJson('/api/v1/experiences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'Engineer');
    }

    public function test_it_shows_a_single_experience(): void
    {
        $exp = $this->make(['role' => ['en' => 'Backend Dev']]);

        $this->getJson("/api/v1/experiences/{$exp->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'Backend Dev');
    }

    public function test_role_is_returned_in_the_requested_locale(): void
    {
        $exp = $this->make(['role' => ['en' => 'Engineer', 'uz' => 'Muhandis', 'ru' => 'Инженер']]);

        $this->getJson("/api/v1/experiences/{$exp->id}?lang=uz")
            ->assertOk()->assertJsonPath('data.role', 'Muhandis');

        $this->getJson("/api/v1/experiences/{$exp->id}?lang=ru")
            ->assertOk()->assertJsonPath('data.role', 'Инженер');
    }

    public function test_guests_cannot_create_experiences(): void
    {
        $this->postJson('/api/v1/experiences', [
            'company' => 'X',
            'role' => ['en' => 'Y'],
            'start_date' => '2020',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('experiences', 0);
    }

    public function test_authenticated_user_can_create_an_experience_with_translations(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/experiences', [
            'company' => 'DOCCO PARTNERS',
            'role' => ['en' => 'Full-stack Engineer', 'uz' => 'Full-stack muhandis'],
            'description' => ['en' => 'Built things.'],
            'start_date' => '2024',
        ])->assertCreated()
            ->assertJsonPath('data.company', 'DOCCO PARTNERS')
            ->assertJsonPath('data.role', 'Full-stack Engineer');

        $this->assertDatabaseCount('experiences', 1);
    }

    public function test_it_validates_required_role(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/experiences', [
            'company' => 'X',
            'start_date' => '2020',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_authenticated_user_can_update_and_delete(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $exp = $this->make();

        $this->patchJson("/api/v1/experiences/{$exp->id}", ['role' => ['en' => 'Lead Engineer']])
            ->assertOk()
            ->assertJsonPath('data.role', 'Lead Engineer');

        $this->deleteJson("/api/v1/experiences/{$exp->id}")->assertNoContent();
        $this->assertDatabaseMissing('experiences', ['id' => $exp->id]);
    }
}
