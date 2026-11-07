<?php

use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->string('badge', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_online')->default(true);
            $table->timestamps();
        });

        foreach (PaymentMethod::DEFAULTS as $code=>$values) {
            DB::table('payment_methods')->insert($values + ['code'=>$code, 'sort_order'=>(array_search($code, array_keys(PaymentMethod::DEFAULTS))+1)*10, 'created_at'=>now(), 'updated_at'=>now()]);
        }

        // Keep the exact provider for new transactions instead of mapping every non-MoMo method to VNPay.
        Schema::table('payments', fn (Blueprint $table) => $table->string('method', 40)->default('cash')->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        // Keep the wider payments.method column so historical provider codes are not discarded.
    }
};
