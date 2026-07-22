<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_social_links_publicly(): void
    {
        SocialLink::factory()->count(3)->create();

        $this->getJson('/api/v1/social-links')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'platform', 'url', 'icon']]]);
    }

    public function test_guests_cannot_create_social_links(): void
    {
        $this->postJson('/api/v1/social-links', ['platform' => 'github', 'url' => 'https://github.com/me'])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_a_social_link(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/social-links', [
            'platform' => 'github',
            'url' => 'https://github.com/me',
            'icon' => 'github',
        ])->assertCreated()->assertJsonPath('data.platform', 'github');

        $this->assertDatabaseHas('social_links', ['platform' => 'github']);
    }

    public function test_url_must_be_valid(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/social-links', ['platform' => 'github', 'url' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('url');
    }

    public function test_authenticated_user_can_delete_a_social_link(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $link = SocialLink::factory()->create();

        $this->deleteJson("/api/v1/social-links/{$link->id}")->assertNoContent();
        $this->assertDatabaseMissing('social_links', ['id' => $link->id]);
    }
}
