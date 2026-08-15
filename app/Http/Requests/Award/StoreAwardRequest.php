<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Award;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAwardRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        // Access is enforced by the auth:sanctum route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['credential_url']);
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
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'title.uz' => ['nullable', 'string', 'max:255'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Award::TYPES)],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.uz' => ['nullable', 'string'],
            'description.ru' => ['nullable', 'string'],
            'issued_on' => ['nullable', 'string', 'max:50'],
            'credential_id' => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
