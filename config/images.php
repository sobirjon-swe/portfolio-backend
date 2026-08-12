<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Uploads
    |--------------------------------------------------------------------------
    |
    | Limits applied to images attached to projects and posts. `mimes` is
    | enforced by sniffing the file contents, not the client-supplied name or
    | Content-Type, so renaming a script to .jpg does not get it past
    | validation. Uploads are stored on the "public" disk under `directory`
    | with a generated name — the original filename is never used as a path.
    |
    */

    'disk' => env('IMAGE_DISK', 'public'),

    'directory' => 'uploads',

    'max_per_owner' => (int) env('IMAGE_MAX_PER_OWNER', 12),

    'max_kilobytes' => (int) env('IMAGE_MAX_KILOBYTES', 5120), // 5 MB

    'mimes' => ['jpeg', 'png', 'webp', 'avif', 'gif'],

    /*
    | Guard against decompression-bomb style uploads: a 40 000 x 40 000 PNG can
    | be a few hundred kilobytes on disk and still exhaust memory downstream.
    */
    'max_dimension' => 6000,

];
