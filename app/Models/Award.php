<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImages;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'title', 'issuer', 'type', 'description',
    'issued_on', 'credential_id', 'credential_url', 'sort_order',
])]
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory, HasImages, HasTranslations;

    /**
     * What the badge on the card says.
     *
     * A certificate is issued for completing something; an award is given for
     * placing above other people. Same table, different claim.
     *
     * @var list<string>
     */
    public const TYPES = ['certificate', 'award'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

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
