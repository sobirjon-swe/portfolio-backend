<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\News\StoreNewsRequest;
use App\Http\Requests\News\UpdateNewsRequest;
use App\Http\Resources\NewsResource;
use App\Services\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return NewsResource::collection($this->service->listPublished());
    }

    /**
     * All items including drafts (admin only).
     */
    public function adminIndex(): AnonymousResourceCollection
    {
        return NewsResource::collection($this->service->listAll());
    }

    public function show(string $slug): NewsResource
    {
        return NewsResource::make($this->service->findPublishedBySlug($slug));
    }

    public function store(StoreNewsRequest $request): JsonResponse
    {
        $news = $this->service->create($request->validated(), $request->user()->id);

        return NewsResource::make($news)
            ->additional(['message' => 'News item created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateNewsRequest $request, int $id): JsonResponse
    {
        $news = $this->service->update($id, $request->validated());

        return NewsResource::make($news)
            ->additional(['message' => 'News item updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
