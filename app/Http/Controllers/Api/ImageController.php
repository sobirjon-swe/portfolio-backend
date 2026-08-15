<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Image\ReorderImagesRequest;
use App\Http\Requests\Image\StoreImageRequest;
use App\Http\Resources\ImageResource;
use App\Models\Award;
use App\Models\Image;
use App\Models\Post;
use App\Models\Project;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gallery management for anything that uses the HasImages trait.
 *
 * The owner type comes from the route, never from the request body, so a
 * client cannot point an upload at an arbitrary model.
 */
class ImageController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const OWNERS = [
        'projects' => Project::class,
        'posts' => Post::class,
        'awards' => Award::class,
    ];

    public function __construct(
        private readonly ImageService $service,
    ) {}

    public function store(StoreImageRequest $request, string $type, int $id): JsonResponse
    {
        $owner = $this->owner($type, $id);
        $alt = $request->input('alt');

        $image = $request->hasFile('image')
            ? $this->service->attachUpload($owner, $request->file('image'), $alt)
            : $this->service->attachUrl($owner, $request->string('url')->toString(), $alt);

        return ImageResource::make($image)
            ->additional(['message' => 'Image added.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function reorder(ReorderImagesRequest $request, string $type, int $id): AnonymousResourceCollection
    {
        $owner = $this->owner($type, $id);

        /** @var array<int, int> $ids */
        $ids = $request->validated('ids');

        return ImageResource::collection($this->service->reorder($owner, $ids));
    }

    public function destroy(int $imageId): Response
    {
        $image = Image::query()->findOrFail($imageId);

        $this->service->delete($image);

        return response()->noContent();
    }

    /**
     * Resolve the gallery owner from the route segment.
     */
    private function owner(string $type, int $id): Model
    {
        abort_unless(array_key_exists($type, self::OWNERS), Response::HTTP_NOT_FOUND);

        return self::OWNERS[$type]::query()->findOrFail($id);
    }
}
