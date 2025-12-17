<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            if (!Schema::hasColumn('food_items', 'status')) {
                $table->enum('status', ['available', 'unavailable'])->default('available')->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            if (Schema::hasColumn('food_items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
