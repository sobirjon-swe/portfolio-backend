<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialLink\StoreSocialLinkRequest;
use App\Http\Requests\SocialLink\UpdateSocialLinkRequest;
use App\Http\Resources\SocialLinkResource;
use App\Services\SocialLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SocialLinkController extends Controller
{
    public function __construct(
        private readonly SocialLinkService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return SocialLinkResource::collection($this->service->list());
    }

    public function show(int $id): SocialLinkResource
    {
        return SocialLinkResource::make($this->service->find($id));
    }

    public function store(StoreSocialLinkRequest $request): JsonResponse
    {
        $socialLink = $this->service->create($request->validated());

        return SocialLinkResource::make($socialLink)
            ->additional(['message' => 'Social link created.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSocialLinkRequest $request, int $id): JsonResponse
    {
        $socialLink = $this->service->update($id, $request->validated());

        return SocialLinkResource::make($socialLink)
            ->additional(['message' => 'Social link updated.'])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
