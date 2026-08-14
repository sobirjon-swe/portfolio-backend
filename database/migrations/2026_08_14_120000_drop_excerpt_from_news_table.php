<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The separate summary went unused in practice: writing a news item meant
 * filling the same idea twice, once short and once long, in three languages.
 * The listing and the link preview now trim the body instead — one field to
 * write, and no second version to keep in sync with the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->jsonb('excerpt')->nullable()->after('slug');
        });
    }
};
