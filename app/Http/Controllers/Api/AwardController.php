<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Award\StoreAwardRequest;
use App\Http\Requests\Award\UpdateAwardRequest;
use App\Http\Resources\AwardResource;
use App\Services\AwardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AwardController extends Controller
{
    public function __construct(
        private readonly AwardService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return AwardResource::collection($this->service->list());
    }

    public function show(int $id): AwardResource
    {
        return AwardResource::make($this->service->find($id));
    }

    public function store(StoreAwardRequest $request): JsonResponse
    {
        $award = $this->service->create($request->validated());

        return AwardResource::make($award)
            ->additional(['message' => 'Award created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateAwardRequest $request, int $id): JsonResponse
    {
        $award = $this->service->update($id, $request->validated());

        return AwardResource::make($award)
            ->additional(['message' => 'Award updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
