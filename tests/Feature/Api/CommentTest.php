<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(): Post
    {
        return Post::factory()->create(['published_at' => now()->subDay()]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'author_name' => 'Aziza',
            'body' => 'Juda foydali maqola, rahmat!',
        ], $overrides);
    }

    // -------------------------------------------------------------- posting --

    public function test_a_visitor_can_leave_a_comment(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Izohingiz yuborildi. Tasdiqlangach chiqadi.');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'author_name' => 'Aziza',
            'is_approved' => false,
        ]);
    }

    public function test_a_new_comment_is_not_visible_until_approved(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload())->assertCreated();

        $this->getJson("/api/v1/posts/{$post->slug}/comments")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_approved_comment_is_visible(): void
    {
        $post = $this->publishedPost();
        Comment::factory()->approved()->for($post)->create(['author_name' => 'Bekzod']);
        Comment::factory()->for($post)->create(['author_name' => 'Pending Petya']);

        $this->getJson("/api/v1/posts/{$post->slug}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'Bekzod');
    }

    public function test_the_public_list_never_exposes_the_visitor_hash(): void
    {
        $post = $this->publishedPost();
        Comment::factory()->approved()->for($post)->create();

        $response = $this->getJson("/api/v1/posts/{$post->slug}/comments")->assertOk();

        $this->assertStringNotContainsString('ip_hash', $response->getContent());
        // Moderation state is admin-only context.
        $response->assertJsonMissingPath('data.0.is_approved');
    }

    public function test_the_name_and_body_are_required(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['author_name', 'body']);
    }

    public function test_the_body_is_capped(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload(['body' => str_repeat('a', 2001)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('body');
    }

    public function test_the_honeypot_rejects_bots(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload(['website' => 'https://spam.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('website');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_commenting_is_rate_limited(): void
    {
        $post = $this->publishedPost();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload())->assertCreated();
        }

        $this->postJson("/api/v1/posts/{$post->slug}/comments", $this->payload())->assertStatus(429);
    }

    public function test_a_draft_post_accepts_no_comments(): void
    {
        $draft = Post::factory()->create(['published_at' => null]);

        $this->postJson("/api/v1/posts/{$draft->slug}/comments", $this->payload())->assertNotFound();
    }

    // ----------------------------------------------------------- moderation --

    public function test_the_moderation_queue_is_admin_only(): void
    {
        $this->getJson('/api/v1/comments')->assertUnauthorized();
    }

    public function test_the_queue_shows_pending_comments_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = $this->publishedPost();

        Comment::factory()->for($post)->create(['author_name' => 'Waiting']);
        Comment::factory()->approved()->for($post)->create(['author_name' => 'Already in']);

        $this->getJson('/api/v1/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'Waiting')
            ->assertJsonPath('meta.pending_total', 1);
    }

    public function test_the_queue_can_show_approved_or_all(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = $this->publishedPost();

        Comment::factory()->for($post)->create();
        Comment::factory()->approved()->for($post)->create();

        $this->getJson('/api/v1/comments?status=approved')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/comments?status=all')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_admin_can_approve_a_comment(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = $this->publishedPost();
        $comment = Comment::factory()->for($post)->create();

        $this->patchJson("/api/v1/comments/{$comment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson("/api/v1/posts/{$post->slug}/comments")->assertJsonCount(1, 'data');
    }

    public function test_an_admin_can_delete_a_comment(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $comment = Comment::factory()->for($this->publishedPost())->create();

        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guests_cannot_approve_or_delete(): void
    {
        $comment = Comment::factory()->for($this->publishedPost())->create();

        $this->patchJson("/api/v1/comments/{$comment->id}/approve")->assertUnauthorized();
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertUnauthorized();
        $this->assertDatabaseCount('comments', 1);
    }

    public function test_deleting_a_post_removes_its_comments(): void
    {
        $post = $this->publishedPost();
        Comment::factory()->count(2)->for($post)->create();

        $post->delete();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_the_post_resource_counts_only_approved_comments(): void
    {
        $post = $this->publishedPost();
        Comment::factory()->approved()->count(2)->for($post)->create();
        Comment::factory()->count(3)->for($post)->create();

        $this->getJson("/api/v1/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.comments_count', 2);
    }
}
