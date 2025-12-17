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
        Schema::create('showtimes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('movie_id'); // FK → movies
            $table->unsignedBigInteger('room_id');  // FK → rooms

            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('base_price', 10, 2);

            $table->enum('status', ['active', 'cancelled'])->default('active');

            $table->timestamps();

            $table->foreign('movie_id')->references('id')->on('movies')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
