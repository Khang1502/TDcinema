<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });
        Schema::create('promotion_display_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->default('Khuyến mãi & ưu đãi');
            $table->string('eyebrow', 120)->default('Xem phim với giá tốt hơn');
            $table->string('display_mode', 16)->default('banner');
            $table->boolean('is_visible')->default(true);
            $table->boolean('autoplay')->default(true);
            $table->unsignedTinyInteger('interval_seconds')->default(5);
            $table->timestamps();
        });
        DB::table('promotion_display_settings')->insert([
            'id'=>1, 'title'=>'Khuyến mãi & ưu đãi', 'eyebrow'=>'Xem phim với giá tốt hơn',
            'display_mode'=>'banner', 'is_visible'=>true, 'autoplay'=>true, 'interval_seconds'=>5,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_display_settings');
        Schema::table('promotions', fn (Blueprint $table) => $table->dropColumn(['show_on_home','sort_order']));
    }
};
