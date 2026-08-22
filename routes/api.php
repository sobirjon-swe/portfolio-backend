<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ExperienceController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\LeetCodeController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PageTextController;
use App\Http\Controllers\Api\PageViewController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostLikeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SocialLinkController;
use App\Http\Controllers\Api\TechnologyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /*
     * Authentication.
     */
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    /*
     * Public read-only endpoints.
     */
    Route::get('technologies', [TechnologyController::class, 'index']);
    Route::get('technologies/{id}', [TechnologyController::class, 'show'])->whereNumber('id');

    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');

    Route::get('posts', [PostController::class, 'index']);

    // Declared before posts/{slug} so "…/comments" is not read as a slug.
    Route::get('posts/{slug}/comments', [CommentController::class, 'index']);
    Route::post('posts/{slug}/comments', [CommentController::class, 'store'])->middleware('throttle:comments');
    Route::post('posts/{slug}/like', [PostLikeController::class, 'toggle'])->middleware('throttle:likes');

    Route::get('posts/{slug}', [PostController::class, 'show']); // fetched by slug for SEO

    Route::get('news', [NewsController::class, 'index']);
    Route::get('news/{slug}', [NewsController::class, 'show']); // fetched by slug for SEO

    // The downloadable CV.
    Route::get('resume', [ResumeController::class, 'show']);

    Route::get('skills', [SkillController::class, 'index']);
    Route::get('skills/{id}', [SkillController::class, 'show'])->whereNumber('id');

    Route::get('social-links', [SocialLinkController::class, 'index']);
    Route::get('social-links/{id}', [SocialLinkController::class, 'show'])->whereNumber('id');

    Route::get('experiences', [ExperienceController::class, 'index']);
    Route::get('experiences/{id}', [ExperienceController::class, 'show'])->whereNumber('id');

    // Awards and certificates — everything published is public, there is no
    // draft state to hide.
    Route::get('awards', [AwardController::class, 'index']);
    Route::get('awards/{id}', [AwardController::class, 'show'])->whereNumber('id');

    // Copy the admin has reworded. Empty on a site nobody has customised.
    Route::get('page-texts', [PageTextController::class, 'index']);

    // Cached LeetCode stats proxy.
    Route::get('leetcode', [LeetCodeController::class, 'stats']);

    // Visitors record their own page views.
    Route::post('page-views', [PageViewController::class, 'store'])->middleware('throttle:page-views');

    // Visitors send project inquiries.
    Route::post('messages', [MessageController::class, 'store'])->middleware('throttle:contact');

    // Vouches from people who have worked with me. Held for moderation.
    Route::get('recommendations', [RecommendationController::class, 'index']);
    Route::post('recommendations', [RecommendationController::class, 'store'])
        ->middleware('throttle:recommendations');

    /*
     * Admin write endpoints (token-protected).
     */
    Route::middleware('auth:sanctum')->group(function () {
        // Bulk-add from the admin's logo picker. Declared before the {id}
        // routes so "bulk" is never read as an id.
        Route::post('technologies/bulk', [TechnologyController::class, 'bulkStore']);
        Route::post('skills/bulk', [SkillController::class, 'bulkStore']);

        Route::post('technologies', [TechnologyController::class, 'store']);
        Route::patch('technologies/{id}', [TechnologyController::class, 'update'])->whereNumber('id');
        Route::delete('technologies/{id}', [TechnologyController::class, 'destroy'])->whereNumber('id');

        Route::post('projects', [ProjectController::class, 'store']);
        Route::patch('projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
        Route::delete('projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');

        Route::get('admin/posts', [PostController::class, 'adminIndex']);
        Route::post('posts', [PostController::class, 'store']);
        Route::patch('posts/{id}', [PostController::class, 'update'])->whereNumber('id');
        Route::delete('posts/{id}', [PostController::class, 'destroy'])->whereNumber('id');

        Route::get('admin/news', [NewsController::class, 'adminIndex']);
        Route::post('news', [NewsController::class, 'store']);
        Route::patch('news/{id}', [NewsController::class, 'update'])->whereNumber('id');
        Route::delete('news/{id}', [NewsController::class, 'destroy'])->whereNumber('id');

        Route::post('skills', [SkillController::class, 'store']);
        Route::patch('skills/{id}', [SkillController::class, 'update'])->whereNumber('id');
        Route::delete('skills/{id}', [SkillController::class, 'destroy'])->whereNumber('id');

        Route::post('social-links', [SocialLinkController::class, 'store']);
        Route::patch('social-links/{id}', [SocialLinkController::class, 'update'])->whereNumber('id');
        Route::delete('social-links/{id}', [SocialLinkController::class, 'destroy'])->whereNumber('id');

        Route::post('experiences', [ExperienceController::class, 'store']);
        Route::patch('experiences/{id}', [ExperienceController::class, 'update'])->whereNumber('id');
        Route::delete('experiences/{id}', [ExperienceController::class, 'destroy'])->whereNumber('id');

        Route::post('awards', [AwardController::class, 'store']);
        Route::patch('awards/{id}', [AwardController::class, 'update'])->whereNumber('id');
        Route::delete('awards/{id}', [AwardController::class, 'destroy'])->whereNumber('id');

        // Aggregated visit statistics.
        Route::get('page-views/stats', [PageViewController::class, 'stats']);

        // Crawler activity, counted from the nginx access log rather than the
        // beacon above — bots do not run the JavaScript that feeds it.
        Route::get('page-views/crawlers', [PageViewController::class, 'crawlers']);

        // Editing the site's own copy.
        Route::get('admin/page-texts', [PageTextController::class, 'adminIndex']);
        Route::put('admin/page-texts', [PageTextController::class, 'update']);

        // Project inquiries (leads).
        Route::get('messages', [MessageController::class, 'index']);
        Route::delete('messages/{id}', [MessageController::class, 'destroy'])->whereNumber('id');

        /*
         * Galleries. The owner type is a route segment constrained to the
         * types ImageController knows, so a client cannot aim an upload at an
         * arbitrary model.
         */
        Route::post('{type}/{id}/images', [ImageController::class, 'store'])
            ->whereIn('type', ['projects', 'posts', 'awards'])->whereNumber('id');
        Route::patch('{type}/{id}/images/order', [ImageController::class, 'reorder'])
            ->whereIn('type', ['projects', 'posts', 'awards'])->whereNumber('id');
        Route::delete('images/{id}', [ImageController::class, 'destroy'])->whereNumber('id');

        // Recommendation moderation. `is_approved` on the update is the
        // moderation gesture, so there is no separate approve route.
        Route::get('admin/recommendations', [RecommendationController::class, 'adminIndex']);
        Route::post('admin/recommendations', [RecommendationController::class, 'adminStore']);
        Route::patch('admin/recommendations/{id}', [RecommendationController::class, 'update'])->whereNumber('id');
        Route::delete('admin/recommendations/{id}', [RecommendationController::class, 'destroy'])->whereNumber('id');

        // Comment moderation.
        Route::get('comments', [CommentController::class, 'adminIndex']);
        Route::patch('comments/{id}/approve', [CommentController::class, 'approve'])->whereNumber('id');
        Route::delete('comments/{id}', [CommentController::class, 'destroy'])->whereNumber('id');

        // Resume upload, one file per language.
        Route::get('resumes', [ResumeController::class, 'index']);
        Route::post('resume', [ResumeController::class, 'store']);
        Route::delete('resume/{locale}', [ResumeController::class, 'destroy'])
            ->whereIn('locale', ['en', 'uz', 'ru']);
    });
});
