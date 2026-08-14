<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Http\Requests\Concerns\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        // Access is enforced by the auth:sanctum route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['github_url', 'live_url']);
    }

    /**
     * PATCH semantics: every field is optional but validated when present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'array'],
            'title.en' => ['required_with:title', 'string', 'max:255'],
            'title.uz' => ['nullable', 'string', 'max:255'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.en' => ['required_with:description', 'string'],
            'description.uz' => ['nullable', 'string'],
            'description.ru' => ['nullable', 'string'],
            'github_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'live_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'technology_ids' => ['sometimes', 'array'],
            'technology_ids.*' => ['integer', 'exists:technologies,id'],
        ];
    }
}
