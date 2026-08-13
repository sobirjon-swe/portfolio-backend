<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Services\ResumeService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResumeController extends Controller
{
    public function __construct(
        private readonly ResumeService $service,
    ) {}

    /**
     * Public: details of the current CV, or 404 before one is uploaded.
     */
    public function show(): JsonResponse
    {
        $resume = $this->service->current();

        if ($resume === null) {
            return response()->json(['message' => 'Rezyume hali yuklanmagan.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => ResumeResource::make($resume)]);
    }

    /**
     * Admin: upload a CV, replacing the previous one.
     */
    public function store(StoreResumeRequest $request): JsonResponse
    {
        $resume = $this->service->replace($request->file('file'));

        return response()->json([
            'data' => ResumeResource::make($resume),
            'message' => 'Rezyume yangilandi.',
        ], Response::HTTP_CREATED);
    }

    public function destroy(): Response
    {
        $this->service->delete();

        return response()->noContent();
    }
}
