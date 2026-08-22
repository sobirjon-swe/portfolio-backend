<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint: any visitor may send a message.
        return true;
    }

    /**
     * Tidy both channels before the rules see them, so a visitor is not
     * rejected over punctuation they had no way to know about.
     *
     * Telegram is pasted in every shape there is — a profile link, a handle
     * with the @, a handle without it — and all of them mean the same account.
     * Phone numbers arrive with spaces, dashes and brackets that carry no
     * information once the digits are known.
     */
    protected function prepareForValidation(): void
    {
        $patch = [];

        $telegram = $this->input('telegram');

        if (is_string($telegram)) {
            $handle = trim($telegram);
            // Strip a profile URL down to the handle it points at.
            $handle = (string) preg_replace('#^(https?://)?(www\.)?(t\.me|telegram\.me|telegram\.dog)/#i', '', $handle);
            $handle = ltrim(trim($handle), '@');

            $patch['telegram'] = $handle === '' ? null : '@'.$handle;
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $digits = trim($phone);
            // Keep a leading +, drop the separators around the digits.
            $digits = (string) preg_replace('/(?!^\+)[^\d]/', '', $digits);

            $patch['phone'] = $digits === '' || $digits === '+' ? null : $digits;
        }

        if ($patch !== []) {
            $this->merge($patch);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],

            // Neither is required on its own, but a message with no way to
            // reply outside email is the case this is here to prevent — so at
            // least one of the two has to be filled in.
            'telegram' => [
                'nullable',
                'required_without:phone',
                'string',
                // Telegram's own rule: 5-32 characters, letters, digits and
                // underscores, after the @ this request adds back.
                'regex:/^@[A-Za-z0-9_]{5,32}$/',
            ],
            'phone' => [
                'nullable',
                'required_without:telegram',
                'string',
                // Digits only by the time this runs, optionally with a
                // leading +. Long enough to be a real number, short enough to
                // stay inside E.164.
                'regex:/^\+?\d{7,15}$/',
            ],

            'budget' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            // Honeypot: real users leave this empty; bots fill it.
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

            // The default wording for required_without names the other field
            // in English and reads as though the form is broken. Both sides
            // say the same thing, so whichever one fires is understandable on
            // its own.
            'telegram.required_without' => 'Telegram yoki telefon raqamidan birini kiriting.',
            'phone.required_without' => 'Telegram yoki telefon raqamidan birini kiriting.',

            'telegram.regex' => 'Telegram username 5–32 ta harf, raqam yoki pastki chiziqdan iborat bo‘lishi kerak.',
            'phone.regex' => 'Telefon raqamini to‘liq kiriting, masalan +998901234567.',
        ];
    }
}
