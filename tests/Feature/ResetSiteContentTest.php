<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Message;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * This command destroys production data, so the tests that matter most are the
 * ones asserting what it does *not* touch: an admin who cannot sign back in,
 * or a CV that has to be re-uploaded, would each be worse than the mess the
 * command exists to clear.
 */
class ResetSiteContentTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): User
    {
        $user = User::factory()->create();

        $post = Post::factory()->create(['user_id' => $user->id]);
        Comment::factory()->count(3)->create(['post_id' => $post->id]);
        Technology::factory()->count(5)->create();
        Skill::factory()->count(2)->create();
        SocialLink::factory()->count(4)->create();
        Message::factory()->count(2)->create();
        PageView::factory()->count(10)->create();
        Resume::query()->create([
            'path' => 'documents/cv.pdf',
            'original_name' => 'Sobirjon_CV.pdf',
            'size' => 12345,
        ]);

        return $user;
    }

    public function test_it_keeps_the_admin_account_and_the_cv(): void
    {
        $user = $this->seedContent();

        $this->artisan('site:reset', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Resume::query()->count());
    }

    public function test_it_empties_content_correspondence_and_analytics(): void
    {
        $this->seedContent();

        $this->artisan('site:reset', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, Comment::query()->count());
        $this->assertSame(0, Technology::query()->count());
        $this->assertSame(0, Skill::query()->count());
        $this->assertSame(0, SocialLink::query()->count());
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, PageView::query()->count());
    }

    public function test_dry_run_removes_nothing(): void
    {
        $this->seedContent();

        $this->artisan('site:reset', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(1, Post::query()->count());
        $this->assertSame(3, Comment::query()->count());
        $this->assertSame(10, PageView::query()->count());
    }

    public function test_declining_the_prompt_removes_nothing(): void
    {
        $this->seedContent();

        $this->artisan('site:reset')
            ->expectsConfirmation('Remove 27 row(s)? This cannot be undone without a backup.', 'no')
            ->expectsOutputToContain('Cancelled')
            ->assertSuccessful();

        $this->assertSame(1, Post::query()->count());
        $this->assertSame(2, Message::query()->count());
    }

    public function test_running_on_an_already_empty_site_is_harmless(): void
    {
        $user = User::factory()->create();

        $this->artisan('site:reset', ['--force' => true])->assertSuccessful();
        $this->artisan('site:reset', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_it_reports_what_it_keeps_before_asking(): void
    {
        $this->seedContent();

        $this->artisan('site:reset', ['--dry-run' => true])
            ->expectsOutputToContain('Kept:')
            ->expectsOutputToContain('users')
            ->expectsOutputToContain('resumes')
            ->assertSuccessful();
    }
}
