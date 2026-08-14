<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The admin editor needs every language at once; the public site only
        // ever wants the one the visitor is reading in.
        $allLocales = $request->boolean('all_locales');

        return [
            'id' => $this->id,
            'title' => $allLocales ? $this->getTranslations('title') : $this->title,
            'slug' => $this->slug,
            'excerpt' => $allLocales ? $this->getTranslations('excerpt') : $this->excerpt,
            'content' => $allLocales ? $this->getTranslations('content') : $this->content,
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
