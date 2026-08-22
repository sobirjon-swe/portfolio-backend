<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The table answered "how many hits" and nothing else: the admin dashboard
     * could not say how many *people* those hits came from, when they arrived,
     * or where from. Everything added here is derived at write time from data
     * the request already carries, so no extra round trip is spent per visit.
     *
     * `referrer` holds the origin host only (never the full URL) — enough to
     * tell LinkedIn from Google without recording the visitor's browsing path.
     */
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('referrer', 255)->nullable()->after('page');
            $table->string('device', 16)->nullable()->after('user_agent');
            $table->string('browser', 40)->nullable()->after('device');
            $table->string('platform', 40)->nullable()->after('browser');
            $table->boolean('is_bot')->default(false)->after('platform');
        });

        Schema::table('page_views', function (Blueprint $table) {
            // The dashboard's every query is "humans, within a date window",
            // so the two columns are indexed together rather than separately.
            $table->index(['is_bot', 'created_at'], 'page_views_bot_created_index');
            // Unique-visitor counts are a DISTINCT over this column per window.
            $table->index('ip_hash', 'page_views_ip_hash_index');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex('page_views_bot_created_index');
            $table->dropIndex('page_views_ip_hash_index');
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn(['referrer', 'device', 'browser', 'platform', 'is_bot']);
        });
    }
};
