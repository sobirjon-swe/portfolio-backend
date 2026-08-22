<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aziza Karimova',
            'email' => 'aziza@example.com',
            // The form now requires a reply channel beyond email.
            'telegram' => '@aziza_dev',
            'budget' => '$3k–5k',
            'body' => 'Kompaniyamiz uchun landing kerak.',
        ], $overrides);
    }

    public function test_a_visitor_can_send_an_inquiry_without_auth(): void
    {
        $this->postJson('/api/v1/messages', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Xabaringiz yuborildi. Tez orada bog‘lanaman!');

        $this->assertDatabaseHas('messages', [
            'email' => 'aziza@example.com',
            'is_read' => false,
        ]);
    }

    public function test_the_response_does_not_echo_the_stored_message_back(): void
    {
        // The endpoint is public; returning the record would let anyone confirm
        // what was persisted. Only an acknowledgement should come back.
        $this->postJson('/api/v1/messages', $this->payload())
            ->assertCreated()
            ->assertJsonMissingPath('data');
    }

    public function test_name_email_and_body_are_required(): void
    {
        $this->postJson('/api/v1/messages', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'body']);
    }

    public function test_the_email_must_be_well_formed(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_the_body_is_capped(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['body' => str_repeat('a', 5001)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('body');
    }

    public function test_the_honeypot_field_rejects_bots(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['website' => 'https://spam.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('website');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_the_honeypot_value_is_never_persisted(): void
    {
        // An empty honeypot passes `prohibited`, but must still be stripped
        // before the payload reaches the model.
        $this->postJson('/api/v1/messages', $this->payload(['website' => '']))
            ->assertCreated();

        $this->assertArrayNotHasKey('website', Message::query()->sole()->getAttributes());
    }

    public function test_submissions_are_rate_limited(): void
    {
        // The `contact` limiter allows 5 per minute per IP.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/messages', $this->payload())->assertCreated();
        }

        $this->postJson('/api/v1/messages', $this->payload())
            ->assertStatus(429);

        $this->assertDatabaseCount('messages', 5);
    }

    public function test_guests_cannot_list_inquiries(): void
    {
        Message::factory()->count(2)->create();

        $this->getJson('/api/v1/messages')->assertUnauthorized();
    }

    public function test_guests_cannot_delete_an_inquiry(): void
    {
        $message = Message::factory()->create();

        $this->deleteJson("/api/v1/messages/{$message->id}")->assertUnauthorized();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_an_admin_lists_inquiries_newest_first(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Message::factory()->create(['name' => 'Older', 'created_at' => now()->subDay()]);
        Message::factory()->create(['name' => 'Newer', 'created_at' => now()]);

        $this->getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    public function test_the_listing_is_paginated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Message::factory()->count(25)->create();

        $this->getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonCount(20, 'data') // MessageService::DEFAULT_PER_PAGE
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/messages?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_per_page_cannot_be_used_to_pull_the_whole_table(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Message::factory()->count(120)->create();

        // Anything above MAX_PER_PAGE is clamped rather than honoured.
        $this->getJson('/api/v1/messages?per_page=5000')
            ->assertOk()
            ->assertJsonCount(100, 'data');
    }

    public function test_an_admin_can_delete_an_inquiry(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $message = Message::factory()->create();

        $this->deleteJson("/api/v1/messages/{$message->id}")->assertNoContent();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_deleting_a_missing_inquiry_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/messages/999')->assertNotFound();
    }
}
