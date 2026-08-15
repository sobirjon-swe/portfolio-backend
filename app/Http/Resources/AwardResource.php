<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Award;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Award
 */
class AwardResource extends JsonResource
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
            'issuer' => $this->issuer,
            'type' => $this->type,
            'description' => $allLocales ? $this->getTranslations('description') : $this->description,
            'issued_on' => $this->issued_on,
            'credential_id' => $this->credential_id,
            'credential_url' => $this->credential_url,
            'sort_order' => $this->sort_order,
            // The certificate scan the card shows, and the rest of the gallery
            // behind it — same shape projects and posts already use.
            'cover_image' => $this->cover_image,
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
