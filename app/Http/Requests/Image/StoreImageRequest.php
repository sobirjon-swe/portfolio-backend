<?php

declare(strict_types=1);

namespace App\Http\Requests\Image;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreImageRequest extends FormRequest
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
        $maxKb = (int) config('images.max_kilobytes');
        $maxDimension = (int) config('images.max_dimension');
        $mimes = implode(',', (array) config('images.mimes'));

        return [
            // `image` and `mimes` both sniff the file contents, so a renamed
            // script does not get through on its extension.
            'image' => [
                'required_without:url',
                'file',
                'image',
                "mimes:{$mimes}",
                "max:{$maxKb}",
                "dimensions:max_width={$maxDimension},max_height={$maxDimension}",
            ],
            'url' => ['required_without:image', 'url', 'max:2048'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('image') && filled($this->input('url'))) {
                $validator->errors()->add('image', 'Send either a file or a URL, not both.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required_without' => 'Attach a file or provide an image URL.',
            'url.required_without' => 'Attach a file or provide an image URL.',
        ];
    }
}
