<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Image
 */
class ImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->resolved_url,
            'alt' => $this->alt,
            'sort_order' => $this->sort_order,
            // Lets the admin show whether an image lives on this server (and so
            // will be deleted with the record) or is only linked.
            'is_uploaded' => $this->path !== null && $this->path !== '',
        ];
    }
}
