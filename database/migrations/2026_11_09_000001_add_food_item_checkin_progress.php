<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_order_items', function (Blueprint $table) {
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->timestamp('fulfilled_at')->nullable();
        });

        // Older orders only tracked fulfillment at order level.
        DB::table('food_order_items')
            ->whereIn('food_order_id', DB::table('food_orders')->whereNotNull('fulfilled_at')->select('id'))
            ->update(['fulfilled_quantity' => DB::raw('quantity')]);
        DB::table('food_order_items')
            ->whereIn('food_order_id', DB::table('food_orders')->whereNotNull('fulfilled_at')->select('id'))
            ->update(['fulfilled_at' => DB::table('food_orders')->whereColumn('food_orders.id', 'food_order_items.food_order_id')->select('fulfilled_at')->limit(1)]);
    }

    public function down(): void
    {
        Schema::table('food_order_items', function (Blueprint $table) {
            $table->dropColumn(['fulfilled_quantity', 'fulfilled_at']);
        });
    }
};
