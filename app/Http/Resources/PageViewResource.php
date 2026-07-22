<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageView
 */
class PageViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page' => $this->page,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
