<?php

namespace Database\Seeders;

use App\Models\{MembershipLevel, Promotion};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MembershipRewardsSeeder extends Seeder
{
    public function run(): void
    {
        // Add defaults without removing levels, vouchers, member points or purchases.
        DB::transaction(function () {
            $levels = [
                [0,'Thành viên mới',0,'blue','Khởi đầu miễn phí, có ưu đãi ngay từ lần đặt vé đầu tiên.',
                    ['Mở khóa voucher chào mừng giảm 15.000 đ','Tích Stars từ đơn thanh toán thành công','Lưu vé và mã QR ngay trong tài khoản']],
                [1000,'Thành viên bạc',5,'silver','Xem phim thường xuyên hơn, tiết kiệm nhiều hơn.',
                    ['Mở khóa voucher Bạc giảm 30.000 đ','Tiếp cận voucher của các mốc Stars thấp hơn','Giữ nguyên Stars khi dùng voucher']],
                [3000,'Thành viên vàng',10,'gold','Ưu đãi nâng tầm cho những buổi xem phim cùng bạn bè.',
                    ['Mở khóa voucher Vàng giảm 50.000 đ','Voucher dùng cho tổng đơn vé và combo','Tiếp cận voucher của các mốc Stars thấp hơn','Giữ nguyên Stars khi dùng voucher']],
                [6000,'Thành viên bạch kim',15,'platinum','Đặc quyền cao cấp cho hành trình điện ảnh của bạn.',
                    ['Mở khóa voucher Bạch kim giảm 80.000 đ','Thêm lựa chọn voucher giảm 30% tổng đơn','Tiếp cận voucher của các mốc Stars thấp hơn','Giữ nguyên Stars khi dùng voucher']],
                [10000,'Thành viên kim cương',20,'diamond','Hạng cao nhất, ưu đãi lớn nhất dành cho khách hàng thân thiết.',
                    ['Mở khóa voucher Kim cương giảm 120.000 đ','Thêm lựa chọn voucher giảm 40% tổng đơn','Tiếp cận voucher của mọi mốc Stars thấp hơn','Giữ nguyên Stars khi dùng voucher']],
            ];
            $legacyBenefits = ['Tích Stars sau mỗi đơn đặt vé thành công','Quản lý vé và mã QR trong tài khoản','Theo dõi các mã khuyến mãi đang diễn ra'];
            foreach ($levels as [$points,$name,$discount,$color,$description,$benefits]) {
                $level = MembershipLevel::firstOrCreate(['min_points'=>$points], ['name'=>$name,'discount_percent'=>$discount,
                    'color'=>$color,'description'=>$description,'benefits'=>$benefits,'is_active'=>true]);
                // Improve initial content while preserving later admin edits.
                if (! $level->wasRecentlyCreated && $level->name === $name && ($level->benefits === null || $level->benefits === $legacyBenefits)) {
                    $level->update(['description'=>$description,'benefits'=>$benefits]);
                }
            }
            foreach ([
                ['STARWELCOME','Quà chào mừng thành viên','fixed',15000,0,500],
                ['STARSILVER','Đặc quyền Bạc · giảm 30.000 đ','fixed',30000,1000,300],
                ['STARGOLD','Đặc quyền Vàng · giảm 50.000 đ','fixed',50000,3000,200],
                ['STARPLATINUM','Đặc quyền Bạch kim · giảm 80.000 đ','fixed',80000,6000,150],
                ['PLATINUM30','Bạch kim · giảm 30% tổng đơn','percent',30,6000,100],
                ['STARDIAMOND','Đặc quyền Kim cương · giảm 120.000 đ','fixed',120000,10000,100],
                ['DIAMOND40','Kim cương · giảm 40% tổng đơn','percent',40,10000,60],
            ] as $order=>[$code,$name,$type,$value,$points,$limit]) {
                Promotion::firstOrCreate(['code'=>$code], ['name'=>$name,'type'=>$type,'value'=>$value,
                    'is_member_gift'=>true,'min_member_points'=>$points,'status'=>'active','show_on_home'=>false,
                    'sort_order'=>$order,'usage_limit'=>$limit,'start_date'=>today(),'end_date'=>today()->addYear(),
                    'description'=>'Áp dụng cho tổng đơn vé và combo khi đủ '.number_format($points,0,',','.').' Stars. Không trừ Stars; không cộng dồn với giảm giá theo hạng hoặc mã khác. Mức giảm tối đa bằng giá trị đơn.']);
            }
        });
    }
}
