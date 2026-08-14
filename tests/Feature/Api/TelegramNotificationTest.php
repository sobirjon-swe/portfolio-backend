<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Point the notifier at a fake bot. The singleton is forgotten first
     * because it captures the credentials when it is first resolved.
     */
    private function configureTelegram(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '99',
        ]);

        $this->app->forgetInstance(TelegramNotifier::class);
    }

    private function messagePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Anvar Karimov',
            'email' => 'anvar@company.uz',
            'budget' => '$500 – $2000',
            'body' => 'We need an internal dashboard.',
        ], $overrides);
    }

    public function test_a_new_message_is_pushed_to_telegram(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postJson('/api/v1/messages', $this->messagePayload())->assertCreated();

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/bottest-token/sendMessage')
                && $request['chat_id'] === '99'
                && str_contains($request['text'], 'Anvar Karimov')
                && str_contains($request['text'], 'anvar@company.uz')
                && str_contains($request['text'], 'We need an internal dashboard.');
        });
    }

    public function test_nothing_is_sent_when_no_bot_is_configured(): void
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.chat_id' => null]);
        $this->app->forgetInstance(TelegramNotifier::class);
        Http::fake();

        $this->postJson('/api/v1/messages', $this->messagePayload())->assertCreated();

        Http::assertNothingSent();
    }

    /**
     * The enquiry is the thing that must survive. Telegram being down is an
     * inconvenience for me and must never become an error for the visitor.
     */
    public function test_a_failing_telegram_does_not_fail_the_submission(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['description' => 'chat not found'], 400)]);

        $this->postJson('/api/v1/messages', $this->messagePayload())->assertCreated();

        $this->assertDatabaseHas('messages', ['email' => 'anvar@company.uz']);
    }

    public function test_an_unreachable_telegram_does_not_fail_the_submission(): void
    {
        $this->configureTelegram();
        Http::fake(fn () => throw new \RuntimeException('connection timed out'));

        $this->postJson('/api/v1/messages', $this->messagePayload())->assertCreated();

        $this->assertDatabaseHas('messages', ['email' => 'anvar@company.uz']);
    }

    /**
     * Sent with parse_mode=HTML, so an unescaped "<" in a visitor's message
     * would have Telegram reject the whole notification as bad markup.
     */
    public function test_markup_characters_in_the_body_are_escaped(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postJson('/api/v1/messages', $this->messagePayload([
            'body' => 'Is <script> & <b>bold</b> handled?',
        ]))->assertCreated();

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['text'], '&lt;script&gt; &amp; &lt;b&gt;bold&lt;/b&gt;')
                && ! str_contains($request['text'], '<script>');
        });
    }

    public function test_a_blank_budget_is_left_out_of_the_alert(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postJson('/api/v1/messages', $this->messagePayload(['budget' => null]))->assertCreated();

        Http::assertSent(fn (Request $request): bool => ! str_contains($request['text'], 'Budjet'));
    }

    public function test_a_new_recommendation_is_pushed_to_telegram(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->postJson('/api/v1/recommendations', [
            'author_name' => 'Dilnoza Karimova',
            'author_role' => 'CTO',
            'author_company' => 'Acme',
            'relationship' => 'manager',
            'body' => 'He owned the payments module end to end and shipped it on time.',
        ])->assertCreated();

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['text'], 'Dilnoza Karimova')
                && str_contains($request['text'], 'CTO')
                && str_contains($request['text'], 'Acme');
        });
    }

    public function test_a_new_comment_is_pushed_to_telegram(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $post = Post::factory()->published()->create(['title' => 'Clean architecture', 'slug' => 'clean-architecture']);

        $this->postJson("/api/v1/posts/{$post->slug}/comments", [
            'author_name' => 'Dilnoza',
            'body' => 'Great write-up.',
        ])->assertCreated();

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['text'], 'Dilnoza')
                && str_contains($request['text'], 'Clean architecture')
                && str_contains($request['text'], 'Great write-up.');
        });
    }
}
