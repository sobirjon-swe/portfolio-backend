<?php

declare(strict_types=1);

namespace App\Http\Requests\Experience;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExperienceRequest extends FormRequest
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
            'company' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'array'],
            'role.en' => ['required_with:role', 'string', 'max:255'],
            'role.uz' => ['nullable', 'string', 'max:255'],
            'role.ru' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.uz' => ['nullable', 'string'],
            'description.ru' => ['nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'string', 'max:50'],
            'end_date' => ['sometimes', 'nullable', 'string', 'max:50'],
            'url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
