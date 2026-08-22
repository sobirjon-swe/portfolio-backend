<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contact form now asks for a second way to reply. Neither Telegram nor
 * phone is required on its own, but a message that offers neither is exactly
 * what the rule exists to stop — so most of these cover the boundary between
 * "one of the two" and "neither".
 */
class MessageContactChannelTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Anvar Karimov',
            'email' => 'anvar@company.uz',
            'body' => 'Kompaniyamiz uchun sayt kerak.',
        ], $overrides);
    }

    public function test_telegram_alone_is_enough(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['telegram' => 'anvar_dev']))
            ->assertCreated();

        $message = Message::query()->sole();
        $this->assertSame('@anvar_dev', $message->telegram);
        $this->assertNull($message->phone);
    }

    public function test_phone_alone_is_enough(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['phone' => '+998901234567']))
            ->assertCreated();

        $message = Message::query()->sole();
        $this->assertSame('+998901234567', $message->phone);
        $this->assertNull($message->telegram);
    }

    public function test_both_together_are_accepted(): void
    {
        $this->postJson('/api/v1/messages', $this->payload([
            'telegram' => '@anvar_dev',
            'phone' => '+998901234567',
        ]))->assertCreated();

        $message = Message::query()->sole();
        $this->assertSame('@anvar_dev', $message->telegram);
        $this->assertSame('+998901234567', $message->phone);
    }

    public function test_neither_is_refused_and_says_why_in_uzbek(): void
    {
        $response = $this->postJson('/api/v1/messages', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('telegram');

        $this->assertSame(
            'Telegram yoki telefon raqamidan birini kiriting.',
            $response->json('errors.telegram.0')
        );

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blank_strings_count_as_neither(): void
    {
        // What the form actually posts when both inputs are left untouched.
        $this->postJson('/api/v1/messages', $this->payload(['telegram' => '', 'phone' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('telegram');
    }

    public function test_a_pasted_profile_link_is_reduced_to_the_handle(): void
    {
        foreach (['https://t.me/anvar_dev', 't.me/anvar_dev', '@anvar_dev', 'anvar_dev'] as $input) {
            Message::query()->delete();

            $this->postJson('/api/v1/messages', $this->payload(['telegram' => $input]))
                ->assertCreated();

            $this->assertSame('@anvar_dev', Message::query()->sole()->telegram, "failed for [{$input}]");
        }
    }

    public function test_a_phone_keeps_its_digits_and_loses_its_punctuation(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['phone' => '+998 (90) 123-45-67']))
            ->assertCreated();

        $this->assertSame('+998901234567', Message::query()->sole()->phone);
    }

    public function test_a_handle_too_short_to_be_real_is_refused(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['telegram' => 'ab']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('telegram');
    }

    public function test_a_number_too_short_to_dial_is_refused(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['phone' => '12345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('phone');
    }

    public function test_email_is_still_required(): void
    {
        $this->postJson('/api/v1/messages', $this->payload(['telegram' => 'anvar_dev', 'email' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_the_admin_list_shows_both_channels(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs(\App\Models\User::factory()->create());

        Message::factory()->create(['telegram' => '@anvar_dev', 'phone' => '+998901234567']);

        $this->getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonPath('data.0.telegram', '@anvar_dev')
            ->assertJsonPath('data.0.phone', '+998901234567');
    }
}
