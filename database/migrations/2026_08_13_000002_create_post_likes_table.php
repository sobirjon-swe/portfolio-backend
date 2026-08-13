<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Likes are anonymous, so the visitor is identified by the same
            // keyed IP digest page_views uses. The unique index is what stops
            // one visitor inflating the count by reloading.
            $table->string('ip_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_likes');
    }
};
