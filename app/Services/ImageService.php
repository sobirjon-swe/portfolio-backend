<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImageService
{
    /**
     * Attach an uploaded file to the owner.
     *
     * @throws ValidationException When the owner is already at its image limit.
     */
    public function attachUpload(Model $owner, UploadedFile $file, ?string $alt = null): Image
    {
        $this->guardLimit($owner);

        // store() generates the filename itself — the client-supplied name is
        // never used to build a path.
        $path = $file->store($this->directoryFor($owner), config('images.disk'));

        return $this->append($owner, ['path' => $path, 'alt' => $alt]);
    }

    /**
     * Attach an externally hosted image to the owner.
     *
     * @throws ValidationException When the owner is already at its image limit.
     */
    public function attachUrl(Model $owner, string $url, ?string $alt = null): Image
    {
        $this->guardLimit($owner);

        return $this->append($owner, ['url' => $url, 'alt' => $alt]);
    }

    public function delete(Image $image): void
    {
        // The model's `deleting` hook removes the file from disk.
        $image->delete();
    }

    /**
     * Apply a new order to the owner's gallery.
     *
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, Image>
     *
     * @throws ValidationException When an id does not belong to this owner.
     */
    public function reorder(Model $owner, array $orderedIds): Collection
    {
        $owned = $owner->images()->pluck('id')->all();
        $unknown = array_diff($orderedIds, $owned);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'ids' => ['One or more images do not belong to this record.'],
            ]);
        }

        DB::transaction(function () use ($owner, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $owner->images()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return $owner->images()->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function append(Model $owner, array $attributes): Image
    {
        $attributes['sort_order'] = (int) $owner->images()->max('sort_order') + 1;

        /** @var Image $image */
        $image = $owner->images()->create($attributes);

        return $image;
    }

    /**
     * @throws ValidationException
     */
    private function guardLimit(Model $owner): void
    {
        $max = (int) config('images.max_per_owner');

        if ($owner->images()->count() >= $max) {
            throw ValidationException::withMessages([
                'image' => ["This record already has the maximum of {$max} images."],
            ]);
        }
    }

    /**
     * Uploads are grouped per owner so a deleted record's files are easy to
     * find, and one record's gallery can never collide with another's.
     */
    private function directoryFor(Model $owner): string
    {
        $type = str(class_basename($owner))->plural()->kebab()->value();

        return trim((string) config('images.directory'), '/')."/{$type}/{$owner->getKey()}";
    }
}
