<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'description' => $allLocales ? $this->getTranslations('description') : $this->description,
            // Accessor over the first gallery image — kept under its original
            // name so existing consumers (cards, OG tags) keep working.
            'cover_image' => $this->cover_image,
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'github_url' => $this->github_url,
            'live_url' => $this->live_url,
            'is_featured' => $this->is_featured,
            'technologies' => TechnologyResource::collection($this->whenLoaded('technologies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
