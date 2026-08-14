<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for adding several catalog entries at once from an admin picker.
 *
 * Shared by both pickers, but the two importers read different fields: a
 * technology keeps its icon key, a skill has no logo and the importer drops it.
 * Validating the field here rather than rejecting it keeps one request class
 * for both, and the mapper decides what is actually stored.
 */
class BulkCatalogRequest extends FormRequest
{
    /**
     * Upper bound on one request. The picker's catalog is around a hundred
     * entries, so this leaves headroom without allowing an unbounded insert.
     */
    private const MAX_ITEMS = 200;

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
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.icon' => ['nullable', 'string', 'max:255'],
            'items.*.category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
