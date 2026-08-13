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
            // Counts come from withCount, so a listing stays one query.
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            // Whether *this* visitor liked it is only resolved for a single
            // post — doing it per row in a listing would be a query each.
            'liked' => $this->when(isset($this->liked), fn (): bool => (bool) $this->liked),
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
