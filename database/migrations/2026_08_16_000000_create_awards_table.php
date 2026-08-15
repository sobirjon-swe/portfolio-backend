<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Awards and certificates.
 *
 * One table for both, unlike news and posts, because the reader does not
 * separate them: a hackathon prize and a cloud certification are the same
 * gesture — someone other than me vouched for the work. The `type` column only
 * decides which word sits on the badge and which filter the card falls under.
 *
 * The certificate scan itself is a gallery image (see the HasImages trait), so
 * an award can carry the certificate, the ceremony photo and the badge without
 * needing a column per file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->jsonb('title');
            $table->string('issuer');
            $table->string('type')->default('certificate'); // certificate | award
            $table->jsonb('description')->nullable();
            // Free text, like experiences' start_date: "2024" and "2024-06" are
            // both honest, and a full date I do not have is not worth inventing.
            $table->string('issued_on')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('credential_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
