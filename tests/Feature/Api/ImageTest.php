<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Image;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // ------------------------------------------------------------- uploads --

    public function test_an_admin_can_upload_an_image_to_a_project(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('screenshot.jpg', 1200, 800),
            'alt' => 'Dashboard view',
        ])
            ->assertCreated()
            ->assertJsonPath('data.alt', 'Dashboard view')
            ->assertJsonPath('data.is_uploaded', true);

        $image = $project->images()->sole();
        $this->assertNotNull($image->path);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_uploads_are_namespaced_per_owner(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('a.png'),
        ])->assertCreated();

        $this->assertStringStartsWith(
            "uploads/projects/{$project->id}/",
            $project->images()->sole()->path,
        );
    }

    public function test_the_original_filename_is_never_used_as_the_stored_path(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('../../evil shell.php.png'),
        ])->assertCreated();

        $path = $project->images()->sole()->path;
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-httpd-php'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');

        $this->assertSame(0, $project->images()->count());
    }

    public function test_a_php_script_renamed_to_jpg_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        // Correct extension and Content-Type, but the bytes are not an image.
        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');
    }

    public function test_an_oversized_upload_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $tooBig = (int) config('images.max_kilobytes') + 1;

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('huge.jpg')->size($tooBig),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');
    }

    public function test_an_over_large_pixel_size_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        // Lower the ceiling rather than generating a real image above the
        // production one: a 6000x6000 bitmap costs ~144 MB to allocate here and
        // would only be testing GD's memory use, not the rule.
        config()->set('images.max_dimension', 50);

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('bomb.png', 120, 120),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');
    }

    public function test_the_gallery_size_is_capped(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $max = (int) config('images.max_per_owner');
        Image::factory()->count($max)->for($project, 'imageable')->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('one-too-many.jpg'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');
    }

    // ---------------------------------------------------------------- urls --

    public function test_an_admin_can_attach_an_external_url(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'url' => 'https://cdn.example.com/shot.png',
        ])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://cdn.example.com/shot.png')
            ->assertJsonPath('data.is_uploaded', false);
    }

    public function test_a_malformed_url_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", ['url' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('url');
    }

    public function test_a_file_and_a_url_cannot_be_sent_together(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('a.jpg'),
            'url' => 'https://cdn.example.com/b.png',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('image');
    }

    public function test_either_a_file_or_a_url_is_required(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", ['alt' => 'nothing attached'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image', 'url']);
    }

    // ------------------------------------------------------------- posts ---

    public function test_posts_use_the_same_gallery(): void
    {
        $this->actingAsAdmin();
        $post = Post::factory()->create();

        $this->postJson("/api/v1/posts/{$post->id}/images", [
            'image' => UploadedFile::fake()->image('figure.png'),
        ])->assertCreated();

        $this->assertSame(1, $post->images()->count());
        $this->assertStringStartsWith("uploads/posts/{$post->id}/", $post->images()->sole()->path);
    }

    public function test_an_unknown_owner_type_is_a_404(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/users/1/images', [
            'url' => 'https://cdn.example.com/a.png',
        ])->assertNotFound();
    }

    // ------------------------------------------------------------ ordering --

    public function test_the_first_image_becomes_the_cover(): void
    {
        $project = Project::factory()->create();
        $first = Image::factory()->for($project, 'imageable')->create(['sort_order' => 0, 'url' => 'https://a.test/1.png']);
        Image::factory()->for($project, 'imageable')->create(['sort_order' => 1, 'url' => 'https://a.test/2.png']);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_image', $first->url)
            ->assertJsonCount(2, 'data.images');
    }

    public function test_an_admin_can_reorder_a_gallery(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();
        $a = Image::factory()->for($project, 'imageable')->create(['sort_order' => 0]);
        $b = Image::factory()->for($project, 'imageable')->create(['sort_order' => 1]);

        $this->patchJson("/api/v1/projects/{$project->id}/images/order", ['ids' => [$b->id, $a->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $b->id)
            ->assertJsonPath('data.1.id', $a->id);

        $this->assertSame($b->url, $project->fresh()->cover_image);
    }

    public function test_reordering_rejects_an_image_from_another_record(): void
    {
        $this->actingAsAdmin();
        $mine = Project::factory()->create();
        $theirs = Project::factory()->create();

        $a = Image::factory()->for($mine, 'imageable')->create();
        $foreign = Image::factory()->for($theirs, 'imageable')->create();

        $this->patchJson("/api/v1/projects/{$mine->id}/images/order", ['ids' => [$foreign->id, $a->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('ids');
    }

    // ------------------------------------------------------------ deletion --

    public function test_deleting_an_image_also_removes_the_file(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('gone.jpg'),
        ])->assertCreated();

        $image = $project->images()->sole();
        Storage::disk('public')->assertExists($image->path);

        $this->deleteJson("/api/v1/images/{$image->id}")->assertNoContent();

        Storage::disk('public')->assertMissing($image->path);
        $this->assertSame(0, $project->images()->count());
    }

    public function test_deleting_the_owner_removes_its_images_and_files(): void
    {
        $this->actingAsAdmin();
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'image' => UploadedFile::fake()->image('orphan.jpg'),
        ])->assertCreated();

        $path = $project->images()->sole()->path;

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();

        // A database-level cascade would have left the file behind.
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, Image::query()->count());
    }

    // --------------------------------------------------------------- auth ---

    public function test_guests_cannot_upload(): void
    {
        $project = Project::factory()->create();

        $this->postJson("/api/v1/projects/{$project->id}/images", [
            'url' => 'https://cdn.example.com/a.png',
        ])->assertUnauthorized();
    }

    public function test_guests_cannot_delete_or_reorder(): void
    {
        $project = Project::factory()->create();
        $image = Image::factory()->for($project, 'imageable')->create();

        $this->deleteJson("/api/v1/images/{$image->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/projects/{$project->id}/images/order", ['ids' => [$image->id]])
            ->assertUnauthorized();
    }
}
