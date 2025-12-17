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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id'); // mỗi vé tương ứng 1 payment (đơn giản)
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'vnpay', 'momo'])->default('cash');
            $table->enum('status', ['paid', 'refunded', 'failed'])->default('paid');

            $table->dateTime('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
