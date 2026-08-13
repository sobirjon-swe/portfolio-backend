<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ResumeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResumeRequest extends FormRequest
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
        $maxKb = (int) config('documents.max_kilobytes');

        return [
            // `mimetypes` checks the sniffed content type, not the extension,
            // so a renamed file does not pass.
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', "max:{$maxKb}"],
            'locale' => ['required', 'string', Rule::in(ResumeService::LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'The resume must be a PDF.',
            'file.mimes' => 'The resume must be a PDF.',
        ];
    }
}
