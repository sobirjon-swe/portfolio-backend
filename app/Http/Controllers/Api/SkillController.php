<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCatalogRequest;
use App\Http\Requests\Skill\StoreSkillRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Services\CatalogImportService;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return SkillResource::collection($this->service->list());
    }

    public function show(int $id): SkillResource
    {
        return SkillResource::make($this->service->find($id));
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = $this->service->create($request->validated());

        return SkillResource::make($skill)
            ->additional(['message' => 'Skill created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSkillRequest $request, int $id): JsonResponse
    {
        $skill = $this->service->update($id, $request->validated());

        return SkillResource::make($skill)
            ->additional(['message' => 'Skill updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Add several skills at once from the skill picker. Names that already
     * exist are skipped rather than duplicated, so re-opening it tops the
     * list up.
     */
    public function bulkStore(BulkCatalogRequest $request, CatalogImportService $import): JsonResponse
    {
        $created = $import->importSkills($request->validated('items'));

        return SkillResource::collection($created)
            ->additional(['message' => "{$created->count()} ta ko‘nikma qo‘shildi."])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
