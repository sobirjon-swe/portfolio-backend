<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function pdf(string $name = 'cv.pdf', int $kilobytes = 120): UploadedFile
    {
        // A real %PDF- header, so the mimetypes rule sees an actual PDF.
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n".str_repeat('0', $kilobytes * 1024),
        );
    }

    private function upload(string $locale, string $name = 'cv.pdf'): void
    {
        $this->postJson('/api/v1/resume', ['file' => $this->pdf($name), 'locale' => $locale])
            ->assertCreated();
    }

    /** Ask as a visitor reading in `$locale`. */
    private function fetchAs(string $locale)
    {
        return $this->getJson('/api/v1/resume', ['Accept-Language' => $locale]);
    }

    // ------------------------------------------------------------- uploading --

    public function test_there_is_no_resume_until_one_is_uploaded(): void
    {
        $this->getJson('/api/v1/resume')->assertNotFound();
    }

    public function test_an_admin_can_upload_a_resume_per_language(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->upload('en', 'CV_en.pdf');
        $this->upload('uz', 'CV_uz.pdf');
        $this->upload('ru', 'CV_ru.pdf');

        $this->assertSame(3, Resume::query()->count());
        $this->assertEqualsCanonicalizing(
            ['en', 'uz', 'ru'],
            Resume::query()->pluck('locale')->all(),
        );
    }

    public function test_the_locale_is_required_and_restricted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('locale');

        $this->postJson('/api/v1/resume', ['file' => $this->pdf(), 'locale' => 'de'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('locale');
    }

    public function test_uploading_one_language_leaves_the_others_alone(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->upload('en', 'CV_en.pdf');
        $this->upload('uz', 'CV_uz_v1.pdf');
        $englishPath = Resume::query()->where('locale', 'en')->sole()->path;

        $this->upload('uz', 'CV_uz_v2.pdf');

        $this->assertSame('CV_en.pdf', Resume::query()->where('locale', 'en')->sole()->original_name);
        Storage::disk('public')->assertExists($englishPath);

        $uz = Resume::query()->where('locale', 'uz')->sole();
        $this->assertSame('CV_uz_v2.pdf', $uz->original_name);
        // Version counts per language, and only one Uzbek row survives.
        $this->assertSame(2, $uz->version);
        $this->assertSame(1, Resume::query()->where('locale', 'uz')->count());
    }

    public function test_replacing_a_language_deletes_its_old_file(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->upload('uz', 'v1.pdf');
        $first = Resume::query()->where('locale', 'uz')->sole()->path;

        $this->upload('uz', 'v2.pdf');

        Storage::disk('public')->assertMissing($first);
    }

    // -------------------------------------------------------------- serving --

    public function test_a_visitor_gets_the_cv_in_their_own_language(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->upload('en', 'CV_en.pdf');
        $this->upload('uz', 'CV_uz.pdf');
        $this->upload('ru', 'CV_ru.pdf');

        foreach (['en' => 'CV_en.pdf', 'uz' => 'CV_uz.pdf', 'ru' => 'CV_ru.pdf'] as $locale => $expected) {
            $this->fetchAs($locale)
                ->assertOk()
                ->assertJsonPath('data.locale', $locale)
                ->assertJsonPath('data.filename', $expected)
                ->assertJsonPath('data.is_fallback', false);
        }
    }

    public function test_the_lang_query_parameter_also_selects_the_language(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->upload('en', 'CV_en.pdf');
        $this->upload('ru', 'CV_ru.pdf');

        $this->getJson('/api/v1/resume?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ru');
    }

    public function test_a_missing_language_falls_back_rather_than_404s(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Only English published.
        $this->upload('en', 'CV_en.pdf');

        $this->fetchAs('uz')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            // The page can tell the visitor this is not their language.
            ->assertJsonPath('data.is_fallback', true);
    }

    public function test_the_fallback_uses_whatever_is_published(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Neither the requested language nor the app fallback exists.
        $this->upload('ru', 'CV_ru.pdf');

        $this->fetchAs('uz')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ru')
            ->assertJsonPath('data.is_fallback', true);
    }

    public function test_the_public_endpoint_reports_real_metadata(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/resume', ['file' => $this->pdf('cv.pdf', 200), 'locale' => 'en'])
            ->assertCreated();

        $response = $this->fetchAs('en')->assertOk();

        $this->assertNotEmpty($response->json('data.url'));
        $this->assertGreaterThan(0, $response->json('data.size'));
        // The page used to print a hardcoded "268 KB"; this is measured.
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB)$/', $response->json('data.size_human'));
    }

    public function test_an_admin_can_list_every_published_language(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->upload('en');
        $this->upload('uz');

        $this->getJson('/api/v1/resumes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------ rejection --

    public function test_a_non_pdf_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => UploadedFile::fake()->image('photo.jpg'), 'locale' => 'en'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('file');
    }

    public function test_a_script_renamed_to_pdf_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // A real file on disk, not UploadedFile::fake(): the fake reports a
        // mime type derived from the *extension*, so it would sail through the
        // very rule this test exists to exercise. A genuine file makes
        // getMimeType() sniff the bytes, which is what happens in production.
        $path = tempnam(sys_get_temp_dir(), 'cv').'.pdf';
        file_put_contents($path, '<?php echo "pwned";');

        try {
            $this->postJson('/api/v1/resume', [
                'file' => new UploadedFile($path, 'cv.pdf', null, null, true),
                'locale' => 'en',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrorFor('file');
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, Resume::query()->count());
    }

    public function test_an_oversized_pdf_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $tooBig = (int) config('documents.max_kilobytes') + 1;

        $this->postJson('/api/v1/resume', ['file' => $this->pdf()->size($tooBig), 'locale' => 'en'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('file');
    }

    public function test_the_stored_path_never_uses_the_original_filename(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->upload('en', '../../evil name.pdf');

        $path = Resume::query()->sole()->path;
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    // ----------------------------------------------------------------- auth --

    public function test_guests_cannot_upload_list_or_delete(): void
    {
        $this->postJson('/api/v1/resume', ['file' => $this->pdf(), 'locale' => 'en'])->assertUnauthorized();
        $this->getJson('/api/v1/resumes')->assertUnauthorized();
        $this->deleteJson('/api/v1/resume/en')->assertUnauthorized();
    }

    public function test_an_admin_can_delete_one_language(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->upload('en', 'CV_en.pdf');
        $this->upload('uz', 'CV_uz.pdf');
        $uzPath = Resume::query()->where('locale', 'uz')->sole()->path;

        $this->deleteJson('/api/v1/resume/uz')->assertNoContent();

        Storage::disk('public')->assertMissing($uzPath);
        $this->assertSame(1, Resume::query()->count());
        // The Uzbek visitor now falls back to English rather than getting a 404.
        $this->fetchAs('uz')->assertOk()->assertJsonPath('data.locale', 'en');
    }
}
