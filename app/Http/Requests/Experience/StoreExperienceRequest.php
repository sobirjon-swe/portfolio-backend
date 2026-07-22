<?php

declare(strict_types=1);

namespace App\Http\Requests\Experience;

use Illuminate\Foundation\Http\FormRequest;

class StoreExperienceRequest extends FormRequest
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
            'company' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'string', 'max:50'],
            'end_date' => ['nullable', 'string', 'max:50'],
            'url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
