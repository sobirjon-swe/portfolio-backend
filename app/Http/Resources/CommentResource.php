<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            // Moderation state and the post it belongs to are only meaningful
            // in the admin, and ip_hash is never exposed at all.
            'is_approved' => $this->when($request->user() !== null, fn (): bool => (bool) $this->is_approved),
            'post' => $this->whenLoaded('post', fn (): array => [
                'id' => $this->post->id,
                'slug' => $this->post->slug,
                'title' => $this->post->title,
            ]),
        ];
    }
}
