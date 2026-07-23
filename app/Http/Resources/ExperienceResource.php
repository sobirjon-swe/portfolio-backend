<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Experience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Experience
 */
class ExperienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allLocales = $request->boolean('all_locales');

        return [
            'id' => $this->id,
            'company' => $this->company,
            'role' => $allLocales ? $this->getTranslations('role') : $this->role,
            'description' => $allLocales ? $this->getTranslations('description') : $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'url' => $this->url,
            'sort_order' => $this->sort_order,
        ];
    }
}
