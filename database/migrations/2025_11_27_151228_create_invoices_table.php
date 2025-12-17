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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');       // khách nào
            $table->unsignedBigInteger('ticket_id');     // vé nào
            $table->unsignedBigInteger('promotion_id')->nullable(); // KM áp dụng

            $table->decimal('total_amount', 12, 2);      // tổng tiền sau KM
            $table->string('invoice_code', 50)->unique(); // mã hóa đơn (HD001...)

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
