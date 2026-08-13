<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Services\ResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ResumeController extends Controller
{
    public function __construct(
        private readonly ResumeService $service,
    ) {}

    /**
     * Public: the CV in the language the visitor is reading, falling back to
     * another language rather than offering nothing.
     *
     * The locale comes from the SetLocale middleware (?lang or Accept-Language).
     */
    public function show(Request $request): JsonResponse
    {
        $requested = app()->getLocale();
        $resume = $this->service->forLocale($requested);

        if ($resume === null) {
            return response()->json(['message' => 'Rezyume hali yuklanmagan.'], Response::HTTP_NOT_FOUND);
        }

        // Lets the resource report whether this is the requested language.
        $request->attributes->set('resume_requested_locale', $requested);

        return response()->json(['data' => ResumeResource::make($resume)]);
    }

    /**
     * Admin: every published language, for the upload slots.
     */
    public function index(): AnonymousResourceCollection
    {
        return ResumeResource::collection($this->service->all());
    }

    /**
     * Admin: upload the CV for one language, replacing that language only.
     */
    public function store(StoreResumeRequest $request): JsonResponse
    {
        $resume = $this->service->replace(
            $request->file('file'),
            $request->string('locale')->toString(),
        );

        return response()->json([
            'data' => ResumeResource::make($resume),
            'message' => "Rezyume ({$resume->locale}) yangilandi.",
        ], Response::HTTP_CREATED);
    }

    public function destroy(string $locale): Response
    {
        $this->service->delete($locale);

        return response()->noContent();
    }
}
