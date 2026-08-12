<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technologies already carried an `icon`; skills did not, so a skill picked
     * from the admin's logo grid had nowhere to record which logo it was.
     */
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
