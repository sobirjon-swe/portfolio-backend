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
            'role' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'string', 'max:50'],
            'end_date' => ['sometimes', 'nullable', 'string', 'max:50'],
            'url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
