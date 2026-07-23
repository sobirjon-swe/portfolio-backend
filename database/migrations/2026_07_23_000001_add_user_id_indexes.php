<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `foreignId()->constrained()` creates the FK constraint but not an index on
 * the column, so ON DELETE CASCADE and "by author" lookups seq-scan. Add the
 * missing indexes on the user_id foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->index('user_id'));
        Schema::table('posts', fn (Blueprint $table) => $table->index('user_id'));
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        Schema::table('posts', fn (Blueprint $table) => $table->dropIndex(['user_id']));
    }
};
