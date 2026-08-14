<?php

declare(strict_types=1);

namespace App\Http\Requests\Recommendation;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Recommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entering or editing a recommendation from the admin panel.
 *
 * Unlike the public form this may set the approval flag — that is the whole
 * moderation gesture — and has no honeypot, since there is no bot behind a
 * token-authenticated request.
 *
 * `sometimes` throughout so the same rules serve POST and PATCH: on create the
 * controller requires the core fields explicitly.
 */
class AdminRecommendationRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        // Access is enforced by the auth:sanctum route middleware.
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'author_name' => [$required, 'string', 'min:2', 'max:60'],
            'author_role' => ['sometimes', 'nullable', 'string', 'max:80'],
            'author_company' => ['sometimes', 'nullable', 'string', 'max:80'],
            'relationship' => ['sometimes', Rule::in(Recommendation::RELATIONSHIPS)],
            'body' => [$required, 'string', 'min:20', 'max:1500'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'is_approved' => ['sometimes', 'boolean'],
        ];
    }
}
