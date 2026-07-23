<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable(['company', 'role', 'description', 'start_date', 'end_date', 'url', 'sort_order'])]
class Experience extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['role', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
