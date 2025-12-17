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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('room_id'); // FK → rooms
            $table->char('seat_row', 2);           // A, B, C…
            $table->unsignedInteger('seat_number'); // 1,2,3
            $table->enum('seat_type', ['normal', 'vip', 'sweetbox'])->default('normal');
            $table->decimal('extra_price', 8, 2)->default(0);

            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
