<?php

declare(strict_types=1);

namespace App\Http\Requests\PageText;

use App\Models\PageText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePageTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is behind auth:sanctum; anyone who reached it may edit copy.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('page-texts.max_length', 2000);

        $rules = [
            'key' => ['required', 'string', 'max:150'],
        ];

        foreach ((array) config('page-texts.locales', []) as $locale) {
            // Nullable, not required: clearing every locale is how the admin
            // asks for the text the app ships with to come back.
            $rules["value.{$locale}"] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $key = (string) $this->input('key');

            if ($key !== '' && ! PageText::isEditable($key)) {
                // Checked here rather than trusted from the UI: the editable
                // list is what stops a request rewriting a button label or
                // an error message that the interface depends on.
                $validator->errors()->add('key', 'Bu matnni tahrirlab bo‘lmaydi.');
            }
        });
    }
}
