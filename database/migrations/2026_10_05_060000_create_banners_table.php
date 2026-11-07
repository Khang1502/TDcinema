<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('image_path', 2048);
            $table->foreignId('movie_id')->nullable()->constrained()->nullOnDelete();
            $table->string('link_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        // Bring the current homepage banners into the manager without copying shared files.
        DB::table('movies')->where('status', 'now_showing')->orderByDesc('release_date')
            ->limit(8)->get(['id', 'title', 'banner_path'])
            ->filter(fn ($movie) => !empty($movie->banner_path))->take(4)->values()
            ->each(function ($movie, $index) {
                DB::table('banners')->insert([
                    'title' => mb_substr($movie->title, 0, 150),
                    'image_path' => $movie->banner_path,
                    'movie_id' => $movie->id,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
