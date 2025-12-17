<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('poster_path')->nullable()->after('title');
            $table->string('banner_path')->nullable()->after('poster_path');

            // trailer_url đã tồn tại; nếu cần giữ thì không drop. Drop poster_url vì chuyển sang lưu path.
            $table->dropColumn('poster_url');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('poster_url', 255)->nullable()->after('actors');
            $table->dropColumn(['poster_path', 'banner_path']);
        });
    }
};
