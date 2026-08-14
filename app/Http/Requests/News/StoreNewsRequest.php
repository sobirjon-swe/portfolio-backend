<?php

declare(strict_types=1);

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsRequest extends FormRequest
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
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'title.uz' => ['nullable', 'string', 'max:255'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'content.en' => ['required', 'string'],
            'content.uz' => ['nullable', 'string'],
            'content.ru' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'], // null = draft
        ];
    }
}
