<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A skill has no logo. The column existed only because the admin offered the
 * technology picker on the skills screen — which is how "Notion" and
 * "WordPress" ended up filed as things I can do. The picker is gone from that
 * screen, and so is the column it filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('name');
        });
    }
};
