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
            // Sent by the client rather than read from a header: the browser
            // attaches Referer to the document request, not to the fetch the
            // page makes afterwards, so the header is empty by the time this
            // endpoint sees it. Only the host survives normalization, so an
            // over-long or hostile URL never reaches storage.
            'referrer' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
