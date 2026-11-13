<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sales_channel', 16)->default('online');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::table('food_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sale_token', 36)->nullable()->unique();
            $table->timestamp('fulfilled_at')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_id')->nullable()->change();
            $table->foreignId('food_order_id')->nullable()->constrained('food_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('tickets')->whereNull('user_id')->exists()
            || DB::table('food_orders')->whereNull('user_id')->exists()
            || DB::table('payments')->whereNull('ticket_id')->exists()
            || DB::table('invoices')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Không thể rollback khi đã có giao dịch khách vãng lai hoặc đơn bắp nước độc lập.');
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_order_id');
            $table->unsignedBigInteger('ticket_id')->nullable(false)->change();
        });
        Schema::table('food_orders', function (Blueprint $table) {
            $table->dropColumn(['fulfilled_at', 'sale_token']);
            $table->dropConstrainedForeignId('sold_by');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable(false)->change());
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('sales_channel');
            $table->dropConstrainedForeignId('sold_by');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
