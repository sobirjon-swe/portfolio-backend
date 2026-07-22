<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageView\StorePageViewRequest;
use App\Http\Resources\PageViewResource;
use App\Services\PageViewService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PageViewController extends Controller
{
    public function __construct(
        private readonly PageViewService $service,
    ) {}

    /**
     * Record a page view (public — called by visitors).
     */
    public function store(StorePageViewRequest $request): JsonResponse
    {
        $pageView = $this->service->record(
            $request->string('page')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return PageViewResource::make($pageView)
            ->additional(['message' => 'Page view recorded.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Aggregated visit statistics (admin only).
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->service->stats()]);
    }
}
