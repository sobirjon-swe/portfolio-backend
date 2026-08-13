<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The CV exists in Uzbek, Russian and English, and the site already knows
     * which one the visitor is reading in. One row per locale, so switching
     * language switches the download.
     *
     * Existing rows are treated as the English CV — that is what the single
     * slot held before this.
     */
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('id');
        });

        // Unique per locale: uploading a new Uzbek CV replaces the Uzbek one
        // and leaves the other languages alone.
        Schema::table('resumes', function (Blueprint $table) {
            $table->unique('locale');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropUnique(['locale']);
            $table->dropColumn('locale');
        });
    }
};
