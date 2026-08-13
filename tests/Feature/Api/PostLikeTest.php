<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostLikeTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(): Post
    {
        return Post::factory()->create(['published_at' => now()->subDay()]);
    }

    public function test_a_visitor_can_like_a_post(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/like")
            ->assertOk()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.likes_count', 1);
    }

    public function test_liking_twice_removes_the_like(): void
    {
        $post = $this->publishedPost();

        $this->postJson("/api/v1/posts/{$post->slug}/like")->assertJsonPath('data.liked', true);

        $this->postJson("/api/v1/posts/{$post->slug}/like")
            ->assertOk()
            ->assertJsonPath('data.liked', false)
            ->assertJsonPath('data.likes_count', 0);
    }

    public function test_one_visitor_cannot_inflate_the_count(): void
    {
        $post = $this->publishedPost();

        // Same IP throughout, so this is one visitor clicking repeatedly.
        foreach (range(1, 5) as $ignored) {
            $this->postJson("/api/v1/posts/{$post->slug}/like");
        }

        $this->assertLessThanOrEqual(1, PostLike::query()->where('post_id', $post->id)->count());
    }

    public function test_different_visitors_each_add_a_like(): void
    {
        $post = $this->publishedPost();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->postJson("/api/v1/posts/{$post->slug}/like")
            ->assertJsonPath('data.likes_count', 1);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])
            ->postJson("/api/v1/posts/{$post->slug}/like")
            ->assertJsonPath('data.likes_count', 2);
    }

    public function test_the_visitor_ip_is_stored_only_as_a_keyed_hash(): void
    {
        $post = $this->publishedPost();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson("/api/v1/posts/{$post->slug}/like")->assertOk();

        $like = PostLike::query()->sole();

        $this->assertSame(
            hash_hmac('sha256', '203.0.113.9', (string) config('app.key')),
            $like->ip_hash,
        );
        $this->assertStringNotContainsString('203.0.113.9', json_encode($like->getAttributes()));
    }

    public function test_the_post_reports_whether_this_visitor_liked_it(): void
    {
        $post = $this->publishedPost();

        $this->getJson("/api/v1/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.liked', false)
            ->assertJsonPath('data.likes_count', 0);

        $this->postJson("/api/v1/posts/{$post->slug}/like")->assertOk();

        $this->getJson("/api/v1/posts/{$post->slug}")
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.likes_count', 1);
    }

    public function test_the_listing_reports_counts_without_a_liked_flag(): void
    {
        $post = $this->publishedPost();
        $this->postJson("/api/v1/posts/{$post->slug}/like")->assertOk();

        // `liked` is per-visitor and would cost a query per row, so the listing
        // deliberately leaves it out.
        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.0.likes_count', 1)
            ->assertJsonMissingPath('data.0.liked');
    }

    public function test_a_draft_post_cannot_be_liked(): void
    {
        $draft = Post::factory()->create(['published_at' => null]);

        $this->postJson("/api/v1/posts/{$draft->slug}/like")->assertNotFound();
    }

    public function test_liking_is_rate_limited(): void
    {
        $post = $this->publishedPost();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/v1/posts/{$post->slug}/like");
        }

        $this->postJson("/api/v1/posts/{$post->slug}/like")->assertStatus(429);
    }

    public function test_deleting_a_post_removes_its_likes(): void
    {
        $post = $this->publishedPost();
        $this->postJson("/api/v1/posts/{$post->slug}/like")->assertOk();

        $post->delete();

        $this->assertDatabaseCount('post_likes', 0);
    }
}
