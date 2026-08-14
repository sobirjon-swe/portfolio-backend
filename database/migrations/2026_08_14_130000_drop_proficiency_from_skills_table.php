<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A percentage against a skill is a number nobody can defend. "PHP 90%" invites
 * the only follow-up that matters — ninety percent of what? — and a recruiter
 * who asks it has already stopped reading the rest.
 *
 * Skills are now what I can do, grouped by area, with the evidence living in
 * the work history where it can be checked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('proficiency');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->unsignedTinyInteger('proficiency')->default(0)->after('icon');
        });
    }
};
