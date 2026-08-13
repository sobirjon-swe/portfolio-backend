<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostLikeService;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    public function __construct(
        private readonly PostLikeService $service,
        private readonly PostService $posts,
    ) {}

    /**
     * Public: like or unlike a published post. Liking twice from the same
     * visitor removes the like rather than adding a second one.
     */
    public function toggle(Request $request, string $slug): JsonResponse
    {
        $post = $this->posts->findPublishedBySlug($slug);

        return response()->json(['data' => $this->service->toggle($post, $request->ip())]);
    }
}
