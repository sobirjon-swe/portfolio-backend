<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('author_name');
            $table->text('body');
            // Nothing is public until it is approved in the admin.
            $table->boolean('is_approved')->default(false);
            // Keyed digest, never the address itself — same treatment as
            // page_views. Used for rate-limit forensics, never displayed.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            // The public list filters on both columns and orders by date.
            $table->index(['post_id', 'is_approved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
