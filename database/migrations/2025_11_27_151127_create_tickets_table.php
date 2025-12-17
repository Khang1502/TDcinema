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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');      // ai đặt vé
            $table->unsignedBigInteger('showtime_id');  // suất chiếu nào
            $table->unsignedBigInteger('seat_id');      // ghế nào

            $table->decimal('price', 10, 2);            // giá vé cuối cùng sau KM
            $table->string('qr_code', 255)->nullable(); // đường dẫn file QR

            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'used'])
                ->default('pending');

            $table->dateTime('booked_at');              // thời điểm đặt vé

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('showtime_id')->references('id')->on('showtimes')->onDelete('cascade');
            $table->foreign('seat_id')->references('id')->on('seats')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
