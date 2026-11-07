<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') DB::statement('ALTER TABLE `users` ENGINE=InnoDB');
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->string('color', 20)->default('blue');
            $table->boolean('is_active')->default(true);
        });
        if (! DB::table('membership_levels')->exists()) {
            DB::table('membership_levels')->insert([
                ['name'=>'Thành viên mới','min_points'=>0,'discount_percent'=>0],
                ['name'=>'Thành viên bạc','min_points'=>1000,'discount_percent'=>5],
                ['name'=>'Thành viên vàng','min_points'=>3000,'discount_percent'=>10],
            ]);
        }
        foreach (DB::table('membership_levels')->orderBy('min_points')->get() as $index => $level) {
            DB::table('membership_levels')->where('id', $level->id)->update([
                'color' => ['blue','silver','gold'][$index % 3],
                'description' => $level->min_points == 0 ? 'Bắt đầu hành trình điện ảnh cùng TD Cinema.' : 'Thêm trải nghiệm, thêm quyền lợi khi đồng hành cùng TD Cinema.',
                'benefits' => json_encode(['Tích Stars sau mỗi đơn đặt vé thành công', 'Quản lý vé và mã QR trong tài khoản', 'Theo dõi các mã khuyến mãi đang diễn ra'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        Schema::create('membership_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->default('TD Stars');
            $table->string('title', 160)->default('Mỗi lần xem phim, thêm một đặc quyền.');
            $table->text('subtitle')->nullable();
            $table->unsignedInteger('earn_unit')->default(1000);
            $table->boolean('is_enabled')->default(true);
            $table->text('terms')->nullable();
            $table->timestamps();
        });
        DB::table('membership_settings')->insert([
            'id'=>1,'name'=>'TD Stars','title'=>'Mỗi lần xem phim, thêm một đặc quyền.',
            'subtitle'=>'Tích Stars, nâng hạng và tận hưởng ưu đãi dành riêng cho bạn tại TD Cinema.',
            'earn_unit'=>1000,'is_enabled'=>true,
            'terms'=>"Stars được cộng sau khi đơn đặt vé hoàn tất, tính trên số tiền cuối cùng sau giảm giá.\nĐiểm bắt đầu được tích từ khi chương trình được kích hoạt; các đơn cũ không được cộng lại tự động.\nGiảm giá theo hạng không cộng dồn với mã khuyến mãi. Khi chọn mã, mức giảm của mã sẽ thay thế ưu đãi theo hạng.\nStars dùng để xét hạng, hiện không đổi trực tiếp thành tiền hoặc dùng để thanh toán.",
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('membership_level_name', 50)->nullable();
            $table->decimal('membership_discount', 12, 2)->default(0);
            $table->unsignedInteger('points_earned')->default(0);
        });
        Schema::create('membership_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->integer('points');
            $table->unsignedInteger('balance_after');
            $table->string('description', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_transactions');
        Schema::dropIfExists('membership_settings');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['membership_level_name','membership_discount','points_earned']));
        Schema::table('membership_levels', fn (Blueprint $table) => $table->dropColumn(['description','benefits','color','is_active']));
    }
};
