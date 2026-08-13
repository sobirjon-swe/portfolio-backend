<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resume Upload
    |--------------------------------------------------------------------------
    |
    | The downloadable CV. Only one is current at a time: uploading a new file
    | replaces the previous one and bumps the version. `mimes` is enforced by
    | sniffing the file contents, so renaming something to .pdf does not get it
    | past validation.
    |
    */

    'disk' => env('DOCUMENT_DISK', 'public'),

    'directory' => 'documents',

    'max_kilobytes' => (int) env('RESUME_MAX_KILOBYTES', 10240), // 10 MB

];
