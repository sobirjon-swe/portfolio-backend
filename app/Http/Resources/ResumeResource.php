<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resume
 */
class ResumeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            // True when the visitor's language has no CV of its own and this
            // is a stand-in, so the page can say which language it handed over.
            'is_fallback' => $this->when(
                $request->attributes->has('resume_requested_locale'),
                fn (): bool => $this->locale !== $request->attributes->get('resume_requested_locale'),
            ),
            'url' => $this->url,
            'filename' => $this->original_name,
            'size' => $this->size,
            // Preformatted so the page does not have to reimplement rounding.
            'size_human' => $this->humanSize(),
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $kb = $bytes / 1024;

        return $kb < 1024
            ? round($kb).' KB'
            : round($kb / 1024, 1).' MB';
    }
}
