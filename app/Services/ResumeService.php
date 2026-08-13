<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resume;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResumeService
{
    public function current(): ?Resume
    {
        return Resume::query()->latest('id')->first();
    }

    /**
     * Store a new CV, replacing whatever was there.
     *
     * The version carries forward from the previous file so the page can show
     * "v3" honestly, while only one PDF is ever kept on disk.
     */
    public function replace(UploadedFile $file): Resume
    {
        return DB::transaction(function () use ($file): Resume {
            $previous = $this->current();

            $path = $file->store((string) config('documents.directory'), config('documents.disk'));

            $resume = Resume::query()->create([
                'path' => $path,
                // The client-supplied name is shown, never used to build a path.
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'version' => ($previous?->version ?? 0) + 1,
            ]);

            // Deleting the old row removes its file through the model hook.
            $previous?->delete();

            return $resume;
        });
    }

    public function delete(): void
    {
        $this->current()?->delete();
    }
}
