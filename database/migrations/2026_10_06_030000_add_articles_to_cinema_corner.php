<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinema_corner_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cinema_corner_items', 'category')) $table->string('category', 16)->default('review')->index();
            if (! Schema::hasColumn('cinema_corner_items', 'content')) $table->text('content')->nullable();
            if (! Schema::hasColumn('cinema_corner_items', 'views_count')) $table->unsignedInteger('views_count')->default(0);
        });
        Schema::table('cinema_corner_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('cinema_corner_settings', 'blog_featured_item_id')) $table->unsignedBigInteger('blog_featured_item_id')->nullable()->index();
        });
        if (! Schema::hasTable('cinema_corner_likes')) {
            Schema::create('cinema_corner_likes', function (Blueprint $table) {
                $table->id();
                // Existing installations may use MyISAM, which does not support foreign keys.
                // Model deletion hooks clean up the article references on all database engines.
                $table->unsignedBigInteger('cinema_corner_item_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex('cinema_corner_likes', 'corner_like_item_user_unique')) {
            Schema::table('cinema_corner_likes', function (Blueprint $table) {
                $table->unique(['cinema_corner_item_id', 'user_id'], 'corner_like_item_user_unique');
                $table->index('user_id');
            });
        }

        // Fill the new tab with editable articles about films already in this catalogue.
        $movies = DB::table('cinema_corner_items')->where('category', 'blog')->exists() ? collect() : DB::table('movies')->whereIn('status', ['now_showing', 'upcoming', 'imax'])
            ->orderByRaw("CASE WHEN title LIKE '%Dune%' THEN 0 ELSE 1 END")
            ->orderByDesc('release_date')->limit(4)->get();
        $topics = [
            ['Khám phá thế giới điện ảnh: ', 'Trước khi xem một bộ phim, bạn có thể tìm hiểu thể loại, thời lượng và trailer để chọn trải nghiệm phù hợp. Cùng khám phá bộ phim có trong danh mục TD Cinema.'],
            ['Chọn phim cho buổi hẹn cuối tuần: ', 'Một buổi xem phim trọn vẹn bắt đầu từ bộ phim phù hợp với sở thích của bạn và người đi cùng. Tham khảo thông tin phim, sau đó chọn rạp và giờ chiếu thuận tiện.'],
            ['Từ trailer đến màn ảnh rộng: ', 'Trailer là lời giới thiệu đầu tiên về không khí và hình ảnh của một bộ phim. Bạn có thể xem trailer tại TD Cinema trước khi quyết định lịch xem.'],
            ['Chuẩn bị cho một buổi xem phim: ', 'Kiểm tra thời lượng và độ tuổi của phim, chọn vị trí ghế thoải mái và đến rạp trước giờ chiếu để có thời gian chuẩn bị.'],
        ];
        $featuredId = null;
        foreach ($movies as $index => $movie) {
            $intro = $topics[$index][1];
            $description = trim(strip_tags($movie->description ?? ''));
            $content = $intro . "\n\n" . ($description ?: 'Xem thông tin phim và trailer trong danh mục của TD Cinema.')
                . "\n\nBạn có thể xem lịch chiếu theo từng rạp, chọn ngày và suất chiếu ở ô Mua vé nhanh bên cạnh. Sau khi đăng nhập, hệ thống sẽ chuyển bạn đến bước chọn ghế.";
            $id = DB::table('cinema_corner_items')->insertGetId([
                'movie_id' => $movie->id, 'category' => 'blog', 'title' => mb_substr($topics[$index][0] . $movie->title, 0, 191),
                'excerpt' => $intro, 'content' => $content, 'sort_order' => $index + 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $featuredId ??= $id;
        }
        if ($featuredId) DB::table('cinema_corner_settings')->where('id', 1)->update(['blog_featured_item_id' => $featuredId]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_corner_likes');
        Schema::table('cinema_corner_settings', fn (Blueprint $table) => $table->dropColumn('blog_featured_item_id'));
        Schema::table('cinema_corner_items', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'content', 'views_count']);
        });
    }
};
