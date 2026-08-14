<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'author_name' => 'Dilnoza Karimova',
            'author_role' => 'CTO',
            'author_company' => 'Acme',
            'relationship' => 'manager',
            'body' => 'He owned the payments module end to end and shipped it on time.',
        ], $overrides);
    }

    public function test_the_public_list_shows_only_approved_ones(): void
    {
        Recommendation::factory()->approved()->count(2)->create();
        Recommendation::factory()->pending()->create();

        $this->getJson('/api/v1/recommendations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_public_list_returns_newest_first(): void
    {
        Recommendation::factory()->approved()->create(['author_name' => 'Older', 'created_at' => now()->subWeek()]);
        Recommendation::factory()->approved()->create(['author_name' => 'Newer', 'created_at' => now()->subHour()]);

        $this->getJson('/api/v1/recommendations')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'Newer')
            ->assertJsonPath('data.1.author_name', 'Older');
    }

    public function test_a_visitor_can_leave_one_and_it_waits_for_approval(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('recommendations', [
            'author_name' => 'Dilnoza Karimova',
            'is_approved' => false,
        ]);

        // Still invisible to everyone else.
        $this->getJson('/api/v1/recommendations')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * The address is kept only as a hash, and never leaves the server —
     * same rule as page views and comments.
     */
    public function test_the_visitors_address_is_hashed_and_never_returned(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload());

        $stored = Recommendation::query()->firstOrFail();

        $this->assertNotNull($stored->ip_hash);
        $this->assertSame(64, strlen($stored->ip_hash));
        $this->assertStringNotContainsString('127.0.0.1', $stored->ip_hash);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/recommendations')
            ->assertOk()
            ->assertJsonMissing(['ip_hash' => $stored->ip_hash]);
    }

    public function test_the_moderation_flag_is_hidden_from_visitors(): void
    {
        Recommendation::factory()->approved()->create();

        $this->getJson('/api/v1/recommendations')
            ->assertOk()
            ->assertJsonMissingPath('data.0.is_approved');
    }

    public function test_the_honeypot_rejects_a_bot(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload(['website' => 'http://spam.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('website');

        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_it_rejects_an_unknown_relationship(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload(['relationship' => 'stranger']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('relationship');
    }

    public function test_it_rejects_a_recommendation_too_short_to_mean_anything(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload(['body' => 'Good guy']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('body');
    }

    public function test_a_linkedin_address_without_a_scheme_is_accepted(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload([
            'linkedin_url' => 'linkedin.com/in/dilnoza',
        ]))->assertCreated();

        $this->assertDatabaseHas('recommendations', ['linkedin_url' => 'https://linkedin.com/in/dilnoza']);
    }

    public function test_role_and_company_are_optional(): void
    {
        $this->postJson('/api/v1/recommendations', $this->payload([
            'author_role' => null,
            'author_company' => null,
        ]))->assertCreated();
    }

    public function test_guests_cannot_reach_the_moderation_queue(): void
    {
        Recommendation::factory()->pending()->create();

        $this->getJson('/api/v1/admin/recommendations')->assertUnauthorized();
        $this->postJson('/api/v1/admin/recommendations', $this->payload())->assertUnauthorized();
    }

    public function test_the_admin_listing_includes_pending_ones_and_counts_them(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Recommendation::factory()->approved()->create();
        Recommendation::factory()->pending()->count(2)->create();

        $this->getJson('/api/v1/admin/recommendations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.pending_total', 2);
    }

    public function test_the_admin_listing_can_be_filtered_to_pending(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Recommendation::factory()->approved()->create();
        Recommendation::factory()->pending()->create();

        $this->getJson('/api/v1/admin/recommendations?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * One I already received elsewhere — it is not waiting on my own approval.
     */
    public function test_one_entered_by_the_admin_is_published_immediately(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/recommendations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson('/api/v1/recommendations')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_approving_is_an_update_of_the_flag(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $pending = Recommendation::factory()->pending()->create();

        $this->patchJson("/api/v1/admin/recommendations/{$pending->id}", ['is_approved' => true])
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson('/api/v1/recommendations')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_approved_one_can_be_taken_back_down(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $live = Recommendation::factory()->approved()->create();

        $this->patchJson("/api/v1/admin/recommendations/{$live->id}", ['is_approved' => false])->assertOk();

        $this->getJson('/api/v1/recommendations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_admin_can_delete_one(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $recommendation = Recommendation::factory()->create();

        $this->deleteJson("/api/v1/admin/recommendations/{$recommendation->id}")->assertNoContent();
        $this->assertDatabaseMissing('recommendations', ['id' => $recommendation->id]);
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/recommendations', $this->payload([
                'author_name' => "Person {$i}",
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/recommendations', $this->payload(['author_name' => 'One too many']))
            ->assertStatus(429);
    }
}
