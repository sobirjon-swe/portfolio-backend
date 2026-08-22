<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crawlers do not run JavaScript, so they never reach the page-views
     * endpoint and are invisible to `page_views` no matter how it is queried.
     * The only place they show up is nginx's access log.
     *
     * `analytics:parse-logs` rolls that log up into one row per day per agent.
     * Rows, not raw lines: the log is rotated away after 14 days, and a daily
     * aggregate keeps the history afterwards for a few hundred bytes a day.
     */
    public function up(): void
    {
        Schema::create('server_hit_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // 'search_engine', 'ai_crawler', 'scanner', 'social', 'tool', 'human'.
            $table->string('category', 20);
            $table->string('agent', 60);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('unique_ips')->default(0);
            $table->timestamps();

            // The parser re-reads whole days and upserts, so a day+agent pair
            // must resolve to exactly one row for the counts to stay absolute.
            $table->unique(['date', 'agent'], 'server_hit_daily_date_agent_unique');
            $table->index(['date', 'category'], 'server_hit_daily_date_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_hit_daily');
    }
};
