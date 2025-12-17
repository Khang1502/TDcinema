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
        Schema::table('cinemas', function (Blueprint $table) {
            // Mở rộng enum status để thêm giá trị maintenance
            $table->enum('status', ['active', 'inactive', 'maintenance'])
                ->default('active')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            // Quay về enum ban đầu
            $table->enum('status', ['active', 'inactive'])
                ->default('active')
                ->change();
        });
    }
};
