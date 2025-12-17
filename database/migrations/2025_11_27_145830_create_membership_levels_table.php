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
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED

            $table->string('name', 50);           // Silver / Gold / Platinum
            $table->unsignedInteger('min_points'); // Từ bao nhiêu điểm trở lên
            $table->unsignedTinyInteger('discount_percent')->default(0); // Giảm giá 0–100%
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_levels');
    }
};
