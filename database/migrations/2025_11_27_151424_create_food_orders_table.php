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
        Schema::create('food_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');      // khách nào
            $table->unsignedBigInteger('ticket_id')->nullable(); // nếu mua kèm vé
            $table->decimal('total_price', 10, 2);
            $table->string('pickup_code', 10)->nullable(); // mã nhận đồ, ví dụ F123
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_orders');
    }
};
