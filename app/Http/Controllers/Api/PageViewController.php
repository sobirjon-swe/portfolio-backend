<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageView\StorePageViewRequest;
use App\Http\Resources\PageViewResource;
use App\Services\CrawlerStatsService;
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
            $request->input('referrer'),
        );

        return PageViewResource::make($pageView)
            ->additional(['message' => 'Page view recorded.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Aggregated visit statistics for real visitors (admin only).
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->service->stats()]);
    }

    /**
     * Crawler activity, read from the nginx access log (admin only).
     *
     * Deliberately a separate endpoint from `stats`: crawlers are counted from
     * a different source and cannot be added to the visitor figures without
     * making both wrong.
     */
    public function crawlers(CrawlerStatsService $crawlers): JsonResponse
    {
        return response()->json(['data' => $crawlers->stats()]);
    }
}
