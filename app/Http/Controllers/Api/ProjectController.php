<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->service->list());
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create(
            $request->validated(),
            $request->user()->id,
        );

        return ProjectResource::make($project)
            ->additional(['message' => 'Project created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(int $id): ProjectResource
    {
        return ProjectResource::make($this->service->find($id));
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->service->update($id, $request->validated());

        return ProjectResource::make($project)
            ->additional(['message' => 'Project updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
