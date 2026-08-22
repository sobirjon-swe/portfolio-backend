<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overrides for the copy that otherwise only exists in the frontend's
     * translation files.
     *
     * Sentences like the About paragraphs were shipped inside the JS bundle,
     * so changing a line meant editing JSON, committing and waiting for a
     * deploy. This table holds only the lines that have actually been edited:
     * the bundled text stays the default, and a key absent here means "no one
     * has changed it". That keeps the site rendering its real copy even if
     * this table is empty or the request for it fails.
     */
    public function up(): void
    {
        Schema::create('page_texts', function (Blueprint $table) {
            $table->id();
            // The i18next path the value replaces, e.g. "about.p1".
            $table->string('key')->unique();
            // Spatie translatable: {"uz": "...", "en": "...", "ru": "..."}.
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_texts');
    }
};
