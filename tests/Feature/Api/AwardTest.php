<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Award;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AwardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function make(array $overrides = []): Award
    {
        return Award::create(array_merge([
            'title' => ['en' => 'AWS Certified Developer'],
            'issuer' => 'Amazon Web Services',
            'type' => 'certificate',
            'description' => ['en' => 'Associate level.'],
            'issued_on' => '2025',
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_it_lists_awards_publicly(): void
    {
        $this->make();

        $this->getJson('/api/v1/awards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'AWS Certified Developer')
            ->assertJsonPath('data.0.type', 'certificate');
    }

    public function test_it_shows_a_single_award(): void
    {
        $award = $this->make(['title' => ['en' => 'Hackathon Winner'], 'type' => 'award']);

        $this->getJson("/api/v1/awards/{$award->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Hackathon Winner')
            ->assertJsonPath('data.type', 'award');
    }

    public function test_the_title_is_returned_in_the_requested_locale(): void
    {
        $award = $this->make([
            'title' => ['en' => 'Best Project', 'uz' => 'Eng yaxshi loyiha', 'ru' => 'Лучший проект'],
        ]);

        $this->getJson("/api/v1/awards/{$award->id}?lang=uz")
            ->assertOk()->assertJsonPath('data.title', 'Eng yaxshi loyiha');

        $this->getJson("/api/v1/awards/{$award->id}?lang=ru")
            ->assertOk()->assertJsonPath('data.title', 'Лучший проект');
    }

    /**
     * Manual order first, then most recent — a certificate earned this year
     * should not sit under one from 2019 just because it was entered later.
     */
    public function test_they_are_ordered_by_sort_order_then_recency(): void
    {
        $this->make(['title' => ['en' => 'Old'], 'issued_on' => '2019']);
        $this->make(['title' => ['en' => 'Recent'], 'issued_on' => '2026']);
        $this->make(['title' => ['en' => 'Pinned'], 'issued_on' => '2020', 'sort_order' => 10]);

        $this->getJson('/api/v1/awards')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pinned')
            ->assertJsonPath('data.1.title', 'Recent')
            ->assertJsonPath('data.2.title', 'Old');
    }

    public function test_guests_cannot_create_awards(): void
    {
        $this->postJson('/api/v1/awards', [
            'title' => ['en' => 'X'],
            'issuer' => 'Y',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('awards', 0);
    }

    public function test_authenticated_user_can_create_an_award_with_translations(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/awards', [
            'title' => ['en' => 'Laravel Certified', 'uz' => 'Laravel sertifikati'],
            'issuer' => 'Laravel',
            'type' => 'certificate',
            'description' => ['en' => 'Passed the exam.'],
            'issued_on' => '2026-03',
            'credential_id' => 'LC-2026-0031',
        ])->assertCreated()
            ->assertJsonPath('data.issuer', 'Laravel')
            ->assertJsonPath('data.title', 'Laravel Certified')
            ->assertJsonPath('data.credential_id', 'LC-2026-0031');

        $this->assertDatabaseCount('awards', 1);
    }

    public function test_it_validates_the_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/awards', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'issuer']);
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/awards', [
            'title' => ['en' => 'X'],
            'issuer' => 'Y',
            'type' => 'medal',
        ])->assertUnprocessable()->assertJsonValidationErrorFor('type');
    }

    public function test_authenticated_user_can_update_and_delete(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $award = $this->make();

        $this->patchJson("/api/v1/awards/{$award->id}", ['title' => ['en' => 'AWS Certified Architect']])
            ->assertOk()
            ->assertJsonPath('data.title', 'AWS Certified Architect');

        $this->deleteJson("/api/v1/awards/{$award->id}")->assertNoContent();
        $this->assertDatabaseMissing('awards', ['id' => $award->id]);
    }

    /**
     * The admin form posts every field it renders, so untouched optional ones
     * arrive as empty strings rather than being absent.
     */
    public function test_it_accepts_the_form_with_every_optional_field_left_blank(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/awards', [
            'title' => ['en' => 'Some Course'],
            'issuer' => 'Coursera',
            'description' => [],
            'issued_on' => '',
            'credential_id' => '',
            'credential_url' => '',
            'sort_order' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.credential_url', null);
    }

    public function test_a_credential_link_typed_without_a_scheme_is_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/awards', [
            'title' => ['en' => 'Some Course'],
            'issuer' => 'Coursera',
            'credential_url' => 'coursera.org/verify/ABC123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.credential_url', 'https://coursera.org/verify/ABC123');
    }

    /**
     * The certificate scan is a gallery image, so the shared image endpoints
     * have to accept `awards` as an owner — and the file has to go with the
     * record when it is deleted.
     */
    public function test_a_certificate_image_can_be_attached_and_is_removed_with_the_award(): void
    {
        Storage::fake(config('images.disk'));
        Sanctum::actingAs(User::factory()->create());

        $award = $this->make();

        $response = $this->postJson("/api/v1/awards/{$award->id}/images", [
            'image' => UploadedFile::fake()->image('certificate.jpg'),
            'alt' => 'AWS certificate',
        ])->assertCreated();

        $this->assertDatabaseCount('images', 1);

        $this->getJson("/api/v1/awards/{$award->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image', $response->json('data.url'))
            ->assertJsonCount(1, 'data.images');

        $this->deleteJson("/api/v1/awards/{$award->id}")->assertNoContent();
        $this->assertDatabaseCount('images', 0);
    }

    public function test_images_cannot_be_aimed_at_an_unknown_owner_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/certificates/1/images', ['url' => 'https://example.com/a.png'])
            ->assertNotFound();
    }
}
