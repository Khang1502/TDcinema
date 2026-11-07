<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showtimes', function (Blueprint $table) {
            $table->index('start_time', 'showtimes_start_time_index');
            $table->index(['room_id', 'start_time'], 'showtimes_room_start_index');
            $table->index(['movie_id', 'start_time'], 'showtimes_movie_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropIndex('showtimes_start_time_index');
            $table->dropIndex('showtimes_room_start_index');
            $table->dropIndex('showtimes_movie_start_index');
        });
    }
};
