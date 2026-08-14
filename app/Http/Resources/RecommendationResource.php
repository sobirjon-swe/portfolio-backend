<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recommendation
 */
class RecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'author_role' => $this->author_role,
            'author_company' => $this->author_company,
            'relationship' => $this->relationship,
            'body' => $this->body,
            'linkedin_url' => $this->linkedin_url,
            'created_at' => $this->created_at?->toIso8601String(),
            // Moderation state is only meaningful to the admin; ip_hash is
            // never exposed at all.
            'is_approved' => $this->when($request->user() !== null, fn (): bool => (bool) $this->is_approved),
        ];
    }
}
