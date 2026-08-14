<?php

declare(strict_types=1);

namespace App\Http\Requests\SocialLink;

use App\Http\Requests\Concerns\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreSocialLinkRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['url']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
        ];
    }
}
