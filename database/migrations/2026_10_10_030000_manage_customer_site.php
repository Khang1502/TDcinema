<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('header_settings', function (Blueprint $table) {
            $table->id(); $table->string('brand_name',120); $table->string('logo_path')->nullable();
            $table->string('login_label',50); $table->string('join_label',70); $table->string('search_placeholder',100);
            $table->boolean('show_search')->default(true); $table->boolean('show_membership')->default(true);
            $table->json('menus'); $table->string('about_title',120); $table->string('about_subtitle',500); $table->text('about_body');
            $table->timestamps();
        });
        $defaults = \App\Models\HeaderSetting::DEFAULTS;
        $defaults['menus'] = json_encode($defaults['menus'], JSON_UNESCAPED_UNICODE);
        DB::table('header_settings')->insert(['id'=>1, ...$defaults, 'created_at'=>now(), 'updated_at'=>now()]);
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('is_member_gift')->default(false);
            $table->unsignedInteger('min_member_points')->default(0);
            $table->text('description')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('header_settings');
        Schema::table('promotions', fn (Blueprint $table) => $table->dropColumn(['is_member_gift','min_member_points','description']));
    }
};
