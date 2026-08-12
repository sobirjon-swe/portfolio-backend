<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCatalogRequest;
use App\Http\Requests\Technology\StoreTechnologyRequest;
use App\Http\Requests\Technology\UpdateTechnologyRequest;
use App\Http\Resources\TechnologyResource;
use App\Services\CatalogImportService;
use App\Services\TechnologyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TechnologyController extends Controller
{
    public function __construct(
        private readonly TechnologyService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TechnologyResource::collection($this->service->list());
    }

    public function store(StoreTechnologyRequest $request): JsonResponse
    {
        $technology = $this->service->create($request->validated());

        return TechnologyResource::make($technology)
            ->additional(['message' => 'Technology created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(int $id): TechnologyResource
    {
        return TechnologyResource::make($this->service->find($id));
    }

    public function update(UpdateTechnologyRequest $request, int $id): JsonResponse
    {
        $technology = $this->service->update($id, $request->validated());

        return TechnologyResource::make($technology)
            ->additional(['message' => 'Technology updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Add several technologies at once from the admin's logo picker.
     * Names that already exist are skipped rather than duplicated.
     */
    public function bulkStore(BulkCatalogRequest $request, CatalogImportService $import): JsonResponse
    {
        $created = $import->importTechnologies($request->validated('items'));

        return TechnologyResource::collection($created)
            ->additional(['message' => "{$created->count()} ta texnologiya qo‘shildi."])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
