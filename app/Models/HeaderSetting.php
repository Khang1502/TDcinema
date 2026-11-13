<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\ImageAsset;

class HeaderSetting extends Model
{
    public const MENUS = ['movies'=>'Phim', 'shop'=>'Star Shop', 'corner'=>'Góc Điện Ảnh', 'events'=>'Sự kiện', 'cinemas'=>'Rạp/Giá vé'];
    public const DEFAULTS = [
        'brand_name'=>'TD Cinema', 'logo_path'=>null, 'login_label'=>'Đăng nhập', 'join_label'=>'Tham gia thành viên',
        'show_search'=>true, 'show_membership'=>true, 'search_placeholder'=>'Tên phim, thể loại…',
        'menus'=>[
            'movies'=>['label'=>'Phim', 'url'=>'/movies', 'is_visible'=>true, 'sort_order'=>0, 'children'=>[]],
            'shop'=>['label'=>'Star Shop', 'url'=>'/star-shop', 'is_visible'=>true, 'sort_order'=>1, 'children'=>[
                ['label'=>'Combo bắp nước', 'url'=>'/star-shop#products', 'description'=>'Chọn món ăn kèm cho buổi xem phim'],
                ['label'=>'Quà tặng điện ảnh', 'url'=>'/star-shop#products', 'description'=>'Xem sản phẩm và bộ sưu tập nổi bật'],
            ]],
            'corner'=>['label'=>'Góc Điện Ảnh', 'url'=>'/#cinema-corner', 'is_visible'=>true, 'sort_order'=>2, 'children'=>[
                ['label'=>'Bình luận phim', 'url'=>'/goc-dien-anh/review', 'description'=>'Góc nhìn và cảm nhận về phim mới'],
                ['label'=>'Blog điện ảnh', 'url'=>'/goc-dien-anh/blog', 'description'=>'Tin tức, hậu trường và câu chuyện phim'],
                ['label'=>'Phim hay tháng', 'url'=>'/movies?status=top_rated', 'description'=>'Phim được xếp theo điểm đánh giá'],
            ]],
            'events'=>['label'=>'Sự kiện', 'url'=>'/khuyen-mai', 'is_visible'=>true, 'sort_order'=>3, 'children'=>[
                ['label'=>'Khuyến mãi đang diễn ra', 'url'=>'/khuyen-mai', 'description'=>'Chọn mã ưu đãi để dùng khi thanh toán'],
                ['label'=>'Ưu đãi thành viên', 'url'=>'/thanh-vien', 'description'=>'Cấp bậc & quyền lợi tại TD Cinema'],
                ['label'=>'Sự kiện đặc biệt', 'url'=>'/#promotions', 'description'=>'Đón xem chương trình sắp diễn ra'],
            ]],
            'cinemas'=>['label'=>'Rạp/Giá vé', 'url'=>'/rap-gia-ve', 'is_visible'=>true, 'sort_order'=>4, 'children'=>[]],
        ],
        'about_title'=>'Giới thiệu TD Cinema',
        'about_subtitle'=>'TD Cinema là hệ thống rạp chiếu phim mô phỏng do sinh viên Huỳnh Gia Khang phát triển trong đồ án chuyên ngành.',
        'about_body'=>"TD Cinema là hệ thống rạp chiếu phim mô phỏng được phát triển trong khuôn khổ đồ án chuyên ngành của sinh viên Huỳnh Gia Khang (ngành Công nghệ Thông tin), với mục tiêu xây dựng một nền tảng đặt vé xem phim trực tuyến hiện đại, thân thiện và mô phỏng sát với mô hình vận hành thực tế của các rạp chiếu phim chuyên nghiệp.\n\nDù chỉ mới được phát triển ở giai đoạn đầu, TD Cinema đã được thiết kế theo cấu trúc của một hệ thống rạp chiếu phim đầy đủ, bao gồm quản lý phim, phòng chiếu, suất chiếu, đặt vé, sơ đồ ghế, mã QR vào rạp và quản lý người dùng. Đây không chỉ là sản phẩm mô phỏng mà còn là nơi để vận dụng tổng hợp kiến thức từ lập trình web, cơ sở dữ liệu, UI/UX đến tư duy thiết kế hệ thống.\n\nTrên website TD Cinema, người dùng có thể dễ dàng xem danh sách phim đang chiếu, phim sắp chiếu, thông tin chi tiết về phim, diễn viên, trailer cũng như lịch chiếu được cập nhật liên tục. Hệ thống đặt vé được xây dựng trực quan: người xem có thể chọn vé theo phim, theo rạp hoặc theo ngày, sau đó lựa chọn chỗ ngồi theo sơ đồ được mô phỏng như rạp thật. Khi hoàn tất thao tác, người dùng nhận được mã xác nhận hoặc mã QR để vào rạp nhanh chóng.\n\nHệ thống còn tích hợp tính năng Đặt Vé Nhanh, hỗ trợ người dùng hoàn tất việc mua vé trong thời gian ngắn — mô phỏng theo những nền tảng rạp chiếu phim chuyên nghiệp như Galaxy Cinema. Bên cạnh đó, TD Cinema cũng mô phỏng các chương trình ưu đãi, giảm giá vé và combo bắp nước nhằm tái hiện hoạt động kinh doanh phổ biến của các chuỗi rạp.\n\nLà người thực hiện hoàn toàn hệ thống, Huỳnh Gia Khang chú trọng vào việc xây dựng cơ sở dữ liệu chuẩn hóa, giao diện dễ sử dụng và trải nghiệm đặt vé mượt mà. TD Cinema không chỉ là một bài tập lập trình mà còn là dự án giúp hoàn thiện kỹ năng phân tích hệ thống, thiết kế kiến trúc, xử lý quy trình nghiệp vụ và phát triển sản phẩm thực tế.\n\nTrong tương lai, dự án TD Cinema có thể tiếp tục mở rộng thêm nhiều chức năng mới, tối ưu hiệu năng và nâng cao trải nghiệm người dùng — hướng đến mô hình của một hệ thống rạp chiếu phim hoàn chỉnh và chuyên nghiệp hơn.",
    ];

    protected $fillable = ['brand_name','logo_path','login_label','join_label','show_search','show_membership','search_placeholder','menus','about_title','about_subtitle','about_body'];

    protected function casts(): array { return ['show_search'=>'boolean','show_membership'=>'boolean','menus'=>'array']; }
    public static function current(): self { return static::find(1) ?? new static(self::DEFAULTS); }
    public function logoUrl(): string { return ImageAsset::url($this->logo_path) ?? asset('assets/images/logo/logo-transparent.png'); }
    public static function linkUrl(string $link): string { return str_starts_with($link, '/') ? url($link) : $link; }
}
