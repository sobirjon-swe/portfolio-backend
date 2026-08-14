<?php

declare(strict_types=1);

namespace App\Http\Requests\Experience;

use App\Http\Requests\Concerns\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreExperienceRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['url']);
        // The column is NOT NULL with a default of 0; a cleared field means
        // "no preference", not an error.
        $this->defaultNumbers(['sort_order' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'role' => ['required', 'array'],
            'role.en' => ['required', 'string', 'max:255'],
            'role.uz' => ['nullable', 'string', 'max:255'],
            'role.ru' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.uz' => ['nullable', 'string'],
            'description.ru' => ['nullable', 'string'],
            'start_date' => ['required', 'string', 'max:50'],
            'end_date' => ['nullable', 'string', 'max:50'],
            'url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
