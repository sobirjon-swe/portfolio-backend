<?php

declare(strict_types=1);

namespace App\Http\Requests\PageView;

use Illuminate\Foundation\Http\FormRequest;

class StorePageViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint: any visitor may record a page view.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['required', 'string', 'max:255'],
        ];
    }
}
