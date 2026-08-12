<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allLocales = $request->boolean('all_locales');

        return [
            'id' => $this->id,
            'title' => $allLocales ? $this->getTranslations('title') : $this->title,
            'slug' => $this->slug,
            'content' => $allLocales ? $this->getTranslations('content') : $this->content,
            'cover_image' => $this->cover_image,
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
