<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('is_monthly_featured')->default(true);
        });

        Schema::create('monthly_movie_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_rating', 3, 1)->default(4.0);
            $table->unsignedInteger('min_reviews')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_movie_settings');
        Schema::table('movies', fn (Blueprint $table) => $table->dropColumn('is_monthly_featured'));
    }
};
