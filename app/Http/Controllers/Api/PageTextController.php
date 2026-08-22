<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageText\UpdatePageTextRequest;
use App\Services\PageTextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageTextController extends Controller
{
    public function __construct(
        private readonly PageTextService $service,
    ) {}

    /**
     * Copy overrides for the visitor's language (public).
     *
     * Returns only what has been edited, so a site nobody has customised
     * answers with an empty object and the frontend keeps its bundled text.
     */
    public function index(Request $request): JsonResponse
    {
        $locales = (array) config('page-texts.locales', []);
        $locale = (string) $request->query('lang', (string) app()->getLocale());

        if (! in_array($locale, $locales, true)) {
            $locale = (string) ($locales[0] ?? 'uz');
        }

        return response()->json(['data' => $this->service->forLocale($locale)]);
    }

    /**
     * Every override in every language, plus which keys may be edited (admin).
     */
    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'data' => [
                'overrides' => $this->service->all(),
                'editable_prefixes' => $this->service->editablePrefixes(),
                'locales' => (array) config('page-texts.locales', []),
            ],
        ]);
    }

    /**
     * Store one key's translations, or clear it back to the bundled text.
     */
    public function update(UpdatePageTextRequest $request): JsonResponse
    {
        $this->service->save(
            $request->string('key')->toString(),
            (array) $request->input('value', []),
        );

        return response()->json(['message' => 'Matn saqlandi.']);
    }
}
