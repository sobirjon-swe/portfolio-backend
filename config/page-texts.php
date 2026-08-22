<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Editable Prefixes
    |--------------------------------------------------------------------------
    |
    | Which parts of the frontend's translation tree the admin may override.
    |
    | Deliberately not "everything": most of the 330 keys are button labels,
    | form errors and empty-state strings, and an accidental edit to one of
    | those breaks an interaction rather than reading as a typo. Only the
    | prose — the copy someone would genuinely want to reword — is exposed.
    |
    | A key qualifies when it equals a prefix or begins with the prefix and a
    | dot, so "about" admits "about.p1" but not "aboutSomethingElse".
    |
    */

    'editable_prefixes' => [
        'hero',      // Landing headline
        'heroSub',   // Landing subtitle
        'cap',       // Section captions across the site
        'about',     // About page paragraphs
        'services',  // What I can build
        'process',   // How I work
        'hr',        // For-HR page
        'audience',  // Audience picker copy
        'more',      // Links page descriptions
        'contact',   // Contact section
        'seo',       // Page titles and meta descriptions
    ],

    /*
    | The locales the admin form offers. Mirrors the frontend's bundles; a
    | value for a locale not listed here is rejected rather than stored where
    | nothing would ever read it.
    */

    'locales' => ['uz', 'en', 'ru'],

    /*
    | Long enough for a paragraph, short enough that the column cannot be used
    | as general-purpose storage.
    */

    'max_length' => 2000,

];
