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
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT

            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Thêm cho đề tài rạp phim
            $table->string('phone', 20)->nullable();

            // Phân quyền: khách / nhân viên / quản lý / admin
            $table->enum('role', ['customer', 'staff', 'manager', 'admin'])
                ->default('customer');

            // Nhân viên/manager thuộc rạp nào (khách thì để null)
            $table->unsignedBigInteger('cinema_id')->nullable();

            // Hạng thành viên (Silver/Gold/Platinum...)
            $table->unsignedBigInteger('membership_level_id')->nullable();

            // Tích điểm & tổng chi tiêu
            $table->unsignedInteger('points')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);

            // Khóa / mở tài khoản
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
