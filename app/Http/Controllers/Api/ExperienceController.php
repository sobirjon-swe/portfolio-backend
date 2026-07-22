<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Experience\StoreExperienceRequest;
use App\Http\Requests\Experience\UpdateExperienceRequest;
use App\Http\Resources\ExperienceResource;
use App\Services\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ExperienceController extends Controller
{
    public function __construct(
        private readonly ExperienceService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ExperienceResource::collection($this->service->list());
    }

    public function show(int $id): ExperienceResource
    {
        return ExperienceResource::make($this->service->find($id));
    }

    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $experience = $this->service->create($request->validated());

        return ExperienceResource::make($experience)
            ->additional(['message' => 'Experience created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateExperienceRequest $request, int $id): JsonResponse
    {
        $experience = $this->service->update($id, $request->validated());

        return ExperienceResource::make($experience)
            ->additional(['message' => 'Experience updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
