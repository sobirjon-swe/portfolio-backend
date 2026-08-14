<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * News sits beside posts rather than inside them: a post is an article that
 * stays worth reading, a news item is an announcement that is interesting on
 * the day and archival afterwards. They are listed, sorted and read
 * differently, so squeezing both through one table and one "type" column would
 * have every query filtering for a distinction the reader already makes.
 *
 * Translatable columns are jsonb here from the start — see
 * 2026_07_23_000000_convert_translatable_columns_to_json for why the older
 * tables had to be converted. On SQLite (the test suite) jsonb degrades to
 * text, which is what spatie/laravel-translatable reads there anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->jsonb('title');
            $table->string('slug')->unique();
            $table->jsonb('excerpt')->nullable();
            $table->jsonb('content');
            $table->timestamp('published_at')->nullable(); // null = draft
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
