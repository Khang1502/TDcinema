<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            if (!Schema::hasColumn('seats', 'status')) {
                $table->boolean('status')->default(1)->after('extra_price');
            }
            // Tránh tạo trùng nhiều lần
            $table->unique(['room_id', 'seat_row', 'seat_number'], 'seats_room_row_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropUnique('seats_room_row_number_unique');
            if (Schema::hasColumn('seats', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
