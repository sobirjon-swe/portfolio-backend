<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Award;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAwardRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['credential_url']);
        $this->defaultNumbers(['sort_order' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'array'],
            'title.en' => ['required_with:title', 'string', 'max:255'],
            'title.uz' => ['nullable', 'string', 'max:255'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'issuer' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Award::TYPES)],
            'description' => ['sometimes', 'nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.uz' => ['nullable', 'string'],
            'description.ru' => ['nullable', 'string'],
            'issued_on' => ['sometimes', 'nullable', 'string', 'max:50'],
            'credential_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credential_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
