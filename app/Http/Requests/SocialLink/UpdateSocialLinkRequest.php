<?php

declare(strict_types=1);

namespace App\Http\Requests\SocialLink;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
