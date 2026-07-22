<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $service,
    ) {}

    /**
     * Public: a visitor sends a project inquiry.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $this->service->create($request->safe()->except('website'));

        return response()->json([
            'message' => 'Xabaringiz yuborildi. Tez orada bog‘lanaman!',
        ], Response::HTTP_CREATED);
    }

    /**
     * Admin: list all inquiries.
     */
    public function index(): AnonymousResourceCollection
    {
        return MessageResource::collection($this->service->list());
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id);

        return response()->noContent();
    }
}
