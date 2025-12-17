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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();              // mã KM
            $table->enum('type', ['percent', 'fixed']);        // %
            $table->unsignedInteger('value');                  // 20 = 20% hoặc 20000đ
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('usage_limit')->nullable(); // số lần giới hạn
            $table->enum('status', ['active', 'expired'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
