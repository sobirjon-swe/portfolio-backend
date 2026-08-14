<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How well I know each tool, on the tools list where the question belongs.
 *
 * Nullable rather than defaulted: a level nobody set is not the same as zero,
 * and the stack section only draws a bar for the entries that actually carry
 * one. That way the list can be filled in gradually instead of every row
 * starting life with a number I did not choose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technologies', function (Blueprint $table) {
            $table->unsignedTinyInteger('proficiency')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('technologies', function (Blueprint $table) {
            $table->dropColumn('proficiency');
        });
    }
};
