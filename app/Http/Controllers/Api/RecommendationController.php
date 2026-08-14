<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\AdminRecommendationRequest;
use App\Http\Requests\Recommendation\StoreRecommendationRequest;
use App\Http\Resources\RecommendationResource;
use App\Models\Recommendation;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly RecommendationService $service,
    ) {}

    /**
     * Public: the approved recommendations.
     */
    public function index(): AnonymousResourceCollection
    {
        return RecommendationResource::collection($this->service->listApproved());
    }

    /**
     * Public: vouch for me. Held for moderation, so the response says so
     * rather than returning something that is not visible yet.
     */
    public function store(StoreRecommendationRequest $request): JsonResponse
    {
        $this->service->submit($request->safe()->except('website'), $request->ip());

        return response()->json([
            'message' => 'Rahmat! Tavsiyangiz tasdiqlangach saytda chiqadi.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Admin: everything, including what is still waiting.
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['pending', 'approved', 'all'], true) ? $status : 'all';

        $perPage = $request->has('per_page') ? (int) $request->input('per_page') : null;

        return RecommendationResource::collection($this->service->listForModeration($status, $perPage))
            ->additional(['meta' => ['pending_total' => $this->service->pendingCount()]]);
    }

    /**
     * Admin: enter one I already received elsewhere.
     */
    public function adminStore(AdminRecommendationRequest $request): JsonResponse
    {
        $recommendation = $this->service->create($request->validated());

        return RecommendationResource::make($recommendation)
            ->additional(['message' => 'Tavsiyanoma qo‘shildi.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Admin: edit, and approve or hide — `is_approved` is the moderation
     * gesture, so there is no separate approve endpoint.
     */
    public function update(AdminRecommendationRequest $request, int $id): JsonResponse
    {
        $recommendation = Recommendation::query()->findOrFail($id);

        return RecommendationResource::make($this->service->update($recommendation, $request->validated()))
            ->additional(['message' => 'Tavsiyanoma yangilandi.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete(Recommendation::query()->findOrFail($id));

        return response()->noContent();
    }
}
