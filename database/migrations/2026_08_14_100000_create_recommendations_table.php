<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouches from people who have actually worked with me.
 *
 * Deliberately not a comment thread on a post: a recommendation is one-way
 * and it is only worth anything if the reader can weigh who said it. Hence
 * role, company and relationship alongside the name — an anonymous "great
 * work!" proves nothing, "CTO at X, hired me" does.
 *
 * Nothing is visible until it is approved. This sits on a portfolio, where an
 * unmoderated form is an invitation to spam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable();
            $table->string('author_company')->nullable();
            // client | colleague | manager | other — see Recommendation::RELATIONSHIPS.
            $table->string('relationship', 20)->default('other');
            $table->text('body');
            // Lets a recruiter verify the person is real.
            $table->string('linkedin_url')->nullable();
            $table->boolean('is_approved')->default(false);
            // Hashed, never the raw address — same reasoning as page views.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['is_approved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
