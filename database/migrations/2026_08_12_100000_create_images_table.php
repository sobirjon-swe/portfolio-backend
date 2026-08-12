<?php

use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects and posts each carried a single `cover_image` URL column, so
     * there was no way to attach a gallery and no way to upload a file at all.
     *
     * This replaces both with one polymorphic table. An image is either an
     * upload (`path`, relative to the public disk) or an external link (`url`);
     * the model's accessor resolves whichever is set. Existing cover values are
     * carried over as the first image of their owner, which keeps the
     * `cover_image` key in the API responses working — it is now an accessor
     * reading the first image instead of a column.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');           // indexes (imageable_type, imageable_id)
            $table->string('path')->nullable();    // uploaded file, on the "public" disk
            $table->string('url')->nullable();     // externally hosted image
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['imageable_type', 'imageable_id', 'sort_order'], 'images_owner_order_index');
        });

        foreach ([Project::class => 'projects', Post::class => 'posts'] as $model => $table) {
            if (! Schema::hasColumn($table, 'cover_image')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('cover_image')
                ->where('cover_image', '!=', '')
                ->orderBy('id')
                ->each(function (object $row) use ($model): void {
                    DB::table('images')->insert([
                        'imageable_type' => $model,
                        'imageable_id' => $row->id,
                        'url' => $row->cover_image,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('cover_image');
            });
        }
    }

    public function down(): void
    {
        foreach (['projects', 'posts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('cover_image')->nullable();
            });
        }

        foreach ([Project::class => 'projects', Post::class => 'posts'] as $model => $table) {
            DB::table('images')
                ->where('imageable_type', $model)
                ->where('sort_order', 0)
                ->orderBy('id')
                ->each(function (object $image) use ($table): void {
                    DB::table($table)
                        ->where('id', $image->imageable_id)
                        ->update(['cover_image' => $image->url ?? $image->path]);
                });
        }

        Schema::dropIfExists('images');
    }
};
