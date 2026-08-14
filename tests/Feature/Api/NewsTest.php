<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_lists_only_published_items(): void
    {
        News::factory()->published()->count(2)->create();
        News::factory()->draft()->create();

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_public_index_returns_newest_first(): void
    {
        News::factory()->create(['title' => 'Older', 'published_at' => now()->subWeek()]);
        News::factory()->create(['title' => 'Newer', 'published_at' => now()->subDay()]);

        $this->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_it_shows_a_published_item_by_slug(): void
    {
        $news = News::factory()->published()->create(['title' => 'We Shipped v2', 'slug' => 'we-shipped-v2']);

        $this->getJson("/api/v1/news/{$news->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'we-shipped-v2')
            ->assertJsonPath('data.is_published', true);
    }

    public function test_draft_items_are_not_reachable_by_slug(): void
    {
        $news = News::factory()->draft()->create(['slug' => 'secret-announcement']);

        $this->getJson("/api/v1/news/{$news->slug}")->assertNotFound();
    }

    public function test_scheduled_items_stay_hidden_until_their_publish_time(): void
    {
        News::factory()->create(['slug' => 'tomorrow', 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/news/tomorrow')->assertNotFound();
    }

    public function test_guests_cannot_create_items(): void
    {
        $this->postJson('/api/v1/news', ['title' => ['en' => 'X'], 'content' => ['en' => 'Y']])
            ->assertUnauthorized();
    }

    public function test_guests_cannot_list_drafts_through_the_admin_route(): void
    {
        News::factory()->draft()->create();

        $this->getJson('/api/v1/admin/news')->assertUnauthorized();
    }

    public function test_admin_index_includes_drafts(): void
    {
        Sanctum::actingAs(User::factory()->create());
        News::factory()->published()->create();
        News::factory()->draft()->create();

        $this->getJson('/api/v1/admin/news')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_creates_a_draft_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/news', [
            'title' => ['en' => 'Conference Talk'],
            'content' => ['en' => 'Some announcement.'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'conference-talk')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('news', [
            'slug' => 'conference-talk',
            'user_id' => $user->id,
            'published_at' => null,
        ]);
    }

    public function test_it_stores_every_locale_and_returns_them_on_request(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/news', [
            'title' => ['en' => 'Release', 'uz' => 'Chiqarildi', 'ru' => 'Релиз'],
            'content' => ['en' => 'Long text.', 'uz' => 'Uzun matn.'],
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/admin/news?all_locales=1')
            ->assertOk()
            ->assertJsonPath('data.0.title.uz', 'Chiqarildi')
            ->assertJsonPath('data.0.title.ru', 'Релиз')
            ->assertJsonPath('data.0.content.uz', 'Uzun matn.');

        $this->assertNotNull($id);
    }

    public function test_it_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/news', ['content' => ['en' => 'no title']])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('title');
    }

    public function test_slug_collisions_get_a_suffix(): void
    {
        Sanctum::actingAs(User::factory()->create());
        News::factory()->create(['title' => 'Same Title', 'slug' => 'same-title']);

        $this->postJson('/api/v1/news', [
            'title' => ['en' => 'Same Title'],
            'content' => ['en' => 'Different item.'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'same-title-2');
    }

    public function test_updating_title_regenerates_slug(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $news = News::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

        $this->patchJson("/api/v1/news/{$news->id}", ['title' => ['en' => 'New Shiny Title']])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-shiny-title');
    }

    public function test_updating_without_renaming_keeps_the_same_slug(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $news = News::factory()->create(['title' => 'Stable Title', 'slug' => 'stable-title']);

        $this->patchJson("/api/v1/news/{$news->id}", ['title' => ['en' => 'Stable Title']])
            ->assertOk()
            ->assertJsonPath('data.slug', 'stable-title');
    }

    public function test_authenticated_user_can_delete_an_item(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $news = News::factory()->create();

        $this->deleteJson("/api/v1/news/{$news->id}")->assertNoContent();
        $this->assertDatabaseMissing('news', ['id' => $news->id]);
    }
}
