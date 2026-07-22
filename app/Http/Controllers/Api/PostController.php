<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return PostResource::collection($this->service->listPublished());
    }

    /**
     * All posts including drafts (admin only).
     */
    public function adminIndex(): AnonymousResourceCollection
    {
        return PostResource::collection($this->service->listAll());
    }

    public function show(string $slug): PostResource
    {
        return PostResource::make($this->service->findPublishedBySlug($slug));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->service->create($request->validated(), $request->user()->id);

        return PostResource::make($post)
            ->additional(['message' => 'Post created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = $this->service->update($id, $request->validated());

        return PostResource::make($post)
            ->additional(['message' => 'Post updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
