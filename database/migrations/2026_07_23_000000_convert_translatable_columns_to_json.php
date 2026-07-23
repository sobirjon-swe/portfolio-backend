<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts translatable text columns to jsonb for spatie/laravel-translatable.
 * Existing scalar values are wrapped under the default locale ("en"); other
 * locales fall back to it until translations are entered via the admin panel.
 *
 * PostgreSQL-specific (project's primary DB).
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $columns = [
        'projects' => ['title', 'description'],
        'posts' => ['title', 'content'],
        'experiences' => ['role', 'description'],
    ];

    public function up(): void
    {
        // Only PostgreSQL needs a real column-type change. SQLite (used by the
        // test suite) is dynamically typed — its text columns already hold the
        // JSON strings spatie/laravel-translatable reads/writes, so no DDL is
        // needed there and any pre-existing scalar is wrapped by the app layer.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col) {
                DB::statement(
                    "ALTER TABLE {$table} ALTER COLUMN {$col} TYPE jsonb "
                    ."USING (CASE WHEN {$col} IS NULL THEN NULL ELSE json_build_object('en', {$col})::jsonb END)"
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // NOTE: this is a lossy rollback — it keeps only the "en" value and
        // discards any uz/ru translations. Do not run against production data
        // that has been translated. COALESCE guards against rows missing "en".
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col) {
                DB::statement(
                    "ALTER TABLE {$table} ALTER COLUMN {$col} TYPE text USING COALESCE({$col}->>'en', '')"
                );
            }
        }
    }
};
