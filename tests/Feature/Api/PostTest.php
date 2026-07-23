<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_lists_only_published_posts(): void
    {
        Post::factory()->published()->count(2)->create();
        Post::factory()->draft()->create();

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_shows_a_published_post_by_slug(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Hello World', 'slug' => 'hello-world']);

        $this->getJson("/api/v1/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'hello-world')
            ->assertJsonPath('data.is_published', true);
    }

    public function test_draft_posts_are_not_reachable_by_slug(): void
    {
        $post = Post::factory()->draft()->create(['slug' => 'secret-draft']);

        $this->getJson("/api/v1/posts/{$post->slug}")->assertNotFound();
    }

    public function test_guests_cannot_create_posts(): void
    {
        $this->postJson('/api/v1/posts', ['title' => 'X', 'content' => 'Y'])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_creates_a_draft_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/posts', [
            'title' => ['en' => 'My First Post'],
            'content' => ['en' => 'Some content.'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'my-first-post')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('posts', [
            'slug' => 'my-first-post',
            'user_id' => $user->id,
            'published_at' => null,
        ]);
    }

    public function test_it_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/posts', ['content' => 'no title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('title');
    }

    public function test_updating_title_regenerates_slug(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

        $this->patchJson("/api/v1/posts/{$post->id}", ['title' => ['en' => 'New Shiny Title']])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-shiny-title');
    }

    public function test_authenticated_user_can_delete_a_post(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $post = Post::factory()->create();

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertNoContent();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
