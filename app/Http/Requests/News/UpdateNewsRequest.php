<?php

declare(strict_types=1);

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'content' => ['sometimes', 'array'],
            'content.en' => ['required_with:content', 'string'],
            'content.uz' => ['nullable', 'string'],
            'content.ru' => ['nullable', 'string'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
