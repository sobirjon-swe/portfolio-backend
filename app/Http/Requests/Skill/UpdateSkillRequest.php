<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'proficiency' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
