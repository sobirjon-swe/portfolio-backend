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

    public function test_there_is_no_resume_until_one_is_uploaded(): void
    {
        $this->getJson('/api/v1/resume')->assertNotFound();
    }

    public function test_an_admin_can_upload_a_resume(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => $this->pdf('Sobirjon_CV.pdf')])
            ->assertCreated()
            ->assertJsonPath('data.filename', 'Sobirjon_CV.pdf')
            ->assertJsonPath('data.version', 1);

        Storage::disk('public')->assertExists(Resume::query()->sole()->path);
    }

    public function test_the_public_endpoint_reports_real_metadata(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/resume', ['file' => $this->pdf('cv.pdf', 200)])->assertCreated();

        $response = $this->getJson('/api/v1/resume')->assertOk();

        $this->assertNotEmpty($response->json('data.url'));
        $this->assertSame('cv.pdf', $response->json('data.filename'));
        $this->assertGreaterThan(0, $response->json('data.size'));
        // The page used to print a hardcoded "268 KB"; this is measured.
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB)$/', $response->json('data.size_human'));
    }

    public function test_uploading_again_replaces_the_file_and_bumps_the_version(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => $this->pdf('v1.pdf')])->assertCreated();
        $first = Resume::query()->sole();

        $this->postJson('/api/v1/resume', ['file' => $this->pdf('v2.pdf')])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.filename', 'v2.pdf');

        // Only one row, and the superseded PDF is gone from disk.
        $this->assertSame(1, Resume::query()->count());
        Storage::disk('public')->assertMissing($first->path);
    }

    public function test_a_non_pdf_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => UploadedFile::fake()->image('photo.jpg')])
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

        $this->postJson('/api/v1/resume', ['file' => $this->pdf()->size($tooBig)])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('file');
    }

    public function test_the_stored_path_never_uses_the_original_filename(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/resume', ['file' => $this->pdf('../../evil name.pdf')])->assertCreated();

        $path = Resume::query()->sole()->path;
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_guests_cannot_upload_or_delete(): void
    {
        $this->postJson('/api/v1/resume', ['file' => $this->pdf()])->assertUnauthorized();
        $this->deleteJson('/api/v1/resume')->assertUnauthorized();
    }

    public function test_an_admin_can_delete_the_resume_and_its_file(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/resume', ['file' => $this->pdf()])->assertCreated();
        $path = Resume::query()->sole()->path;

        $this->deleteJson('/api/v1/resume')->assertNoContent();

        Storage::disk('public')->assertMissing($path);
        $this->getJson('/api/v1/resume')->assertNotFound();
    }
}
