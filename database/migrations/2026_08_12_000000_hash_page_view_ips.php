<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visitor IPs were stored in the clear. Replace the column with a keyed hash
     * so the analytics stay useful for de-duplication without holding on to
     * personal data, and index created_at for the retention pruning query.
     *
     * Existing rows are dropped along with the column: re-hashing them would
     * only launder data that should not have been retained in the first place.
     */
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable()->after('page');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('ip_hash');
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('page');
        });
    }
};
