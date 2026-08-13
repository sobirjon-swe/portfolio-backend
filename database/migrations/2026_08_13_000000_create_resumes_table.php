<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The resume page advertised a file size, a version and an "updated" date
     * that were all hardcoded in the markup, and its download button pointed at
     * "#". This table holds the real thing.
     *
     * Only one row is ever current: uploading replaces the previous file and
     * carries the version forward, so storage does not grow with every edit.
     */
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->string('path');                       // on the documents disk
            $table->string('original_name');              // shown to the visitor
            $table->unsignedInteger('size');              // bytes
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
