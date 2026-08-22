<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PageText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The copy overrides sit in front of every page of the site, so these cover
 * the ways they could do damage: overwriting a key the interface depends on,
 * blanking a paragraph, or serving stale text after an edit.
 */
class PageTextTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_a_site_with_no_edits_returns_nothing(): void
    {
        $this->getJson('/api/v1/page-texts?lang=uz')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_an_edited_line_is_served_in_the_requested_language(): void
    {
        $this->actAsAdmin();

        $this->putJson('/api/v1/admin/page-texts', [
            'key' => 'about.p1',
            'value' => ['uz' => 'Yangi matn', 'en' => 'New text', 'ru' => 'Новый текст'],
        ])->assertOk();

        $this->assertSame('Yangi matn', $this->getJson('/api/v1/page-texts?lang=uz')->assertOk()->json('data')['about.p1']);
        $this->assertSame('New text', $this->getJson('/api/v1/page-texts?lang=en')->assertOk()->json('data')['about.p1']);
    }

    public function test_a_language_left_empty_falls_back_to_the_bundled_text(): void
    {
        $this->actAsAdmin();

        $this->putJson('/api/v1/admin/page-texts', [
            'key' => 'about.p1',
            'value' => ['uz' => 'Faqat ozbekcha', 'en' => '', 'ru' => null],
        ])->assertOk();

        // Absent, not empty: an empty string would paint a blank where the
        // shipped sentence should still be showing.
        $this->getJson('/api/v1/page-texts?lang=en')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/page-texts?lang=uz')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_clearing_every_language_restores_the_bundled_text(): void
    {
        $this->actAsAdmin();

        $this->putJson('/api/v1/admin/page-texts', ['key' => 'about.p1', 'value' => ['uz' => 'Vaqtinchalik']])
            ->assertOk();
        $this->assertDatabaseCount('page_texts', 1);

        $this->putJson('/api/v1/admin/page-texts', ['key' => 'about.p1', 'value' => ['uz' => '', 'en' => '', 'ru' => '']])
            ->assertOk();

        $this->assertDatabaseCount('page_texts', 0);
        $this->getJson('/api/v1/page-texts?lang=uz')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_keys_outside_the_editable_list_are_refused(): void
    {
        $this->actAsAdmin();

        // A button label. Rewording it from this endpoint would break an
        // interaction rather than read as a typo, so it is not on offer.
        $this->putJson('/api/v1/admin/page-texts', ['key' => 'form.submit', 'value' => ['uz' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('key');

        $this->assertDatabaseCount('page_texts', 0);
    }

    public function test_a_prefix_is_not_matched_halfway_through_a_word(): void
    {
        $this->actAsAdmin();

        $this->putJson('/api/v1/admin/page-texts', ['key' => 'aboutSomethingElse', 'value' => ['uz' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('key');
    }

    public function test_editing_is_admin_only(): void
    {
        $this->putJson('/api/v1/admin/page-texts', ['key' => 'about.p1', 'value' => ['uz' => 'x']])
            ->assertUnauthorized();

        $this->getJson('/api/v1/admin/page-texts')->assertUnauthorized();
    }

    public function test_an_edit_is_visible_immediately_despite_the_cache(): void
    {
        $this->actAsAdmin();

        $this->putJson('/api/v1/admin/page-texts', ['key' => 'about.p1', 'value' => ['uz' => 'Birinchi']])->assertOk();
        $this->assertSame('Birinchi', $this->getJson('/api/v1/page-texts?lang=uz')->json('data')['about.p1']);

        $this->putJson('/api/v1/admin/page-texts', ['key' => 'about.p1', 'value' => ['uz' => 'Ikkinchi']])->assertOk();

        // Would fail if saving did not drop the cached payload.
        $this->assertSame('Ikkinchi', $this->getJson('/api/v1/page-texts?lang=uz')->json('data')['about.p1']);
    }

    public function test_an_unknown_language_falls_back_rather_than_erroring(): void
    {
        PageText::query()->create(['key' => 'about.p1', 'value' => ['uz' => 'Salom']]);

        $this->assertSame('Salom', $this->getJson('/api/v1/page-texts?lang=de')->assertOk()->json('data')['about.p1']);
    }

    public function test_the_admin_list_returns_every_language_and_the_editable_prefixes(): void
    {
        $this->actAsAdmin();

        PageText::query()->create(['key' => 'about.p1', 'value' => ['uz' => 'A', 'en' => 'B']]);

        $data = $this->getJson('/api/v1/admin/page-texts')->assertOk()->json('data');

        $this->assertSame(['uz' => 'A', 'en' => 'B'], $data['overrides']['about.p1']);
        $this->assertSame(['uz', 'en', 'ru'], $data['locales']);
        $this->assertSame(config('page-texts.editable_prefixes'), $data['editable_prefixes']);
    }
}
