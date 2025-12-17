<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('genre', 100)->nullable();      // ví dụ: "Hành động, Hài"
            $table->unsignedInteger('duration');          // phút
            $table->string('director', 100)->nullable();
            $table->text('actors')->nullable();
            $table->string('poster_url', 255)->nullable();
            $table->string('trailer_url', 255)->nullable();
            $table->date('release_date')->nullable();

            $table->decimal('rating_avg', 3, 1)->default(0); // 0.0 - 5.0
            $table->unsignedInteger('review_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
