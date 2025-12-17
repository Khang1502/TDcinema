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
    Schema::create('rooms', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('cinema_id'); // FK → cinemas
        $table->string('name', 100);
        $table->enum('type', ['2D', '3D', 'IMAX', 'VIP']);
        $table->unsignedInteger('total_seats')->default(0);
        $table->enum('status', ['active', 'maintenance'])->default('active');

        $table->timestamps();

        // Foreign key
        $table->foreign('cinema_id')->references('id')->on('cinemas')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::dropIfExists('rooms');
}

};
