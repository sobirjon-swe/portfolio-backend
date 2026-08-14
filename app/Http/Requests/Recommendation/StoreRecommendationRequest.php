<?php

declare(strict_types=1);

namespace App\Http\Requests\Recommendation;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Recommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A visitor vouching for me. Public, so it is held for moderation and the
 * honeypot applies.
 */
class StoreRecommendationRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['linkedin_url']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'min:2', 'max:60'],
            // Not required, but heavily encouraged in the form: a name with no
            // role or company behind it is not evidence of anything.
            'author_role' => ['nullable', 'string', 'max:80'],
            'author_company' => ['nullable', 'string', 'max:80'],
            'relationship' => ['required', Rule::in(Recommendation::RELATIONSHIPS)],
            'body' => ['required', 'string', 'min:20', 'max:1500'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            // Honeypot: real visitors never see this field, bots fill it.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'Spam aniqlandi.',
        ];
    }
}
