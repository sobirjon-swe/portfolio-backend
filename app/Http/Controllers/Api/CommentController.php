<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Services\CommentService;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentService $service,
        private readonly PostService $posts,
    ) {}

    /**
     * Public: approved comments on a published post.
     */
    public function index(string $slug): AnonymousResourceCollection
    {
        $post = $this->posts->findPublishedBySlug($slug);

        return CommentResource::collection($this->service->listApproved($post));
    }

    /**
     * Public: leave a comment. It is held for moderation, so the response
     * says so rather than returning something that is not visible yet.
     */
    public function store(StoreCommentRequest $request, string $slug): JsonResponse
    {
        $post = $this->posts->findPublishedBySlug($slug);

        $this->service->create($post, $request->safe()->except('website'), $request->ip());

        return response()->json([
            'message' => 'Izohingiz yuborildi. Tasdiqlangach chiqadi.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Admin: the moderation queue.
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['pending', 'approved', 'all'], true) ? $status : 'pending';

        $perPage = $request->has('per_page') ? (int) $request->input('per_page') : null;

        return CommentResource::collection($this->service->listForModeration($status, $perPage))
            ->additional(['meta' => ['pending_total' => $this->service->pendingCount()]]);
    }

    public function approve(int $id): JsonResponse
    {
        $comment = Comment::query()->findOrFail($id);

        return response()->json([
            'data' => CommentResource::make($this->service->approve($comment)),
            'message' => 'Izoh tasdiqlandi.',
        ]);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete(Comment::query()->findOrFail($id));

        return response()->noContent();
    }
}
