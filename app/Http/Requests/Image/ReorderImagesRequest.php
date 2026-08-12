<?php

declare(strict_types=1);

namespace App\Http\Requests\Image;

use Illuminate\Foundation\Http\FormRequest;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the auth:sanctum route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            // Ownership is checked in ImageService::reorder — `exists` alone
            // would happily accept another record's image ids.
            'ids.*' => ['integer', 'distinct', 'exists:images,id'],
        ];
    }
}
