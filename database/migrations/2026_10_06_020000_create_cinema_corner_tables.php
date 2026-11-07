<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cinema_corner_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->string('trailer_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
        Schema::create('cinema_corner_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('eyebrow', 120)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('featured_label', 60);
            $table->string('link_label', 60)->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->unsignedTinyInteger('list_limit')->default(3);
            $table->boolean('is_active')->default(true);
            $table->foreignId('featured_item_id')->nullable()->constrained('cinema_corner_items')->nullOnDelete();
            $table->timestamps();
        });

        // Keep the films currently selected by the homepage when enabling manual management.
        $nowShowing = DB::table('movies')->where('status', 'now_showing')->orderByDesc('release_date')->limit(8)->get();
        $upcoming = DB::table('movies')->where('status', 'upcoming')->orderBy('release_date')->limit(8)->get();
        $featured = $upcoming->first() ?? $nowShowing->first();
        $items = collect($featured ? [$featured] : [])->merge($nowShowing->merge($upcoming)->reject(fn ($movie) => $movie->id === $featured?->id)->take(3));
        $featuredId = null;
        foreach ($items as $index => $movie) {
            $id = DB::table('cinema_corner_items')->insertGetId([
                'movie_id' => $movie->id, 'sort_order' => $index + 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($index === 0) $featuredId = $id;
        }
        DB::table('cinema_corner_settings')->insert([
            'id' => 1, 'title' => 'Góc Điện Ảnh', 'eyebrow' => 'Khám phá những câu chuyện mới',
            'description' => 'Thông tin phim, nội dung nổi bật và trailer dành cho bạn.',
            'featured_label' => 'Phim nổi bật', 'link_label' => 'Khám phá tất cả',
            'link_url' => '/movies?status=all', 'list_limit' => 3, 'is_active' => true,
            'featured_item_id' => $featuredId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_corner_settings');
        Schema::dropIfExists('cinema_corner_items');
    }
};
