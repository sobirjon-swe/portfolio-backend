<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second way to reach whoever wrote in.
     *
     * Email was the only channel captured, and a reply to a cold address is
     * easy to miss or to lose to a spam filter. Both columns are nullable —
     * the "one of the two" rule lives in the form request, where it can say
     * why in the visitor's own language, rather than in a constraint that
     * would only ever surface as a database error.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Telegram usernames are capped at 32 characters; the extra room
            // is for the leading @ and for anything a visitor pastes before
            // normalization trims it back to a handle.
            $table->string('telegram', 64)->nullable()->after('email');
            $table->string('phone', 32)->nullable()->after('telegram');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['telegram', 'phone']);
        });
    }
};
