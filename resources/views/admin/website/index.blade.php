@extends('admin.layouts.app')
@section('title', 'Quản lý trang khách hàng')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/content-settings.css') }}?v={{ filemtime(public_path('assets/css/admin/content-settings.css')) }}">@endpush
@section('content')
@php
$groups = [
    'Doanh thu & thống kê' => [
        ['reports.view','admin.reports.index',[],'Báo cáo tổng hợp','Doanh thu, hoàn tiền, vé, combo, xu hướng và chi tiết giao dịch.','chart-no-axes-combined'],
        ['reports.view','admin.reports.index',['tab'=>'movies'],'Doanh thu theo phim','Theo dõi từng phim, gồm cả phim đã lưu trữ.','film'],
        ['reports.view','admin.reports.index',['tab'=>'cinemas'],'Doanh thu theo rạp','Đối chiếu kết quả của từng cụm rạp trong hệ thống.','building'],
    ],
    'Giao diện & nội dung' => [
        ['header.manage','admin.header.edit',[],'Header & giới thiệu','Đổi logo, tên thương hiệu, menu, liên kết và nội dung giới thiệu.', 'panel-top'],
        ['banners.manage','admin.banners.index',[],'Banner','Ảnh, vị trí trang chủ / rạp, liên kết, thứ tự và trạng thái.','image'],
        ['footer.manage','admin.footer.edit',[],'Footer','Thông tin liên hệ, các cột liên kết, mạng xã hội và bản quyền.', 'panel-bottom'],
        ['cinema-corner.manage','admin.cinema-corner.index',[],'Góc Điện Ảnh','Tiêu đề khu vực, bài nổi bật, nội dung và hình ảnh.', 'newspaper'],
        ['cinema-corner.manage','admin.cinema-corner.index',['category'=>'review'],'Bình luận phim','Thêm, sửa, sắp xếp và ẩn / hiện bài review.', 'newspaper'],
        ['cinema-corner.manage','admin.cinema-corner.index',['category'=>'blog'],'Blog điện ảnh','Quản lý bài viết, nội dung, ảnh và thời gian xuất bản.', 'newspaper'],
    ],
    'Rạp & đặt vé' => [
        ['movies.manage','admin.movies.index',[],'Phim','Thông tin, poster, trailer và trạng thái đang / sắp chiếu.','film'],
        ['movies.manage','admin.genres.index',[],'Thể loại phim','Danh sách thể loại, thứ tự, trạng thái và phim đang sử dụng.','tags'],
        ['cinemas.manage','admin.cinemas.index',[],'Hệ thống rạp','5 khu vực, địa chỉ, ảnh rạp, phòng và trạng thái.','building'],
        ['rooms.manage','admin.rooms.index',[],'Phòng chiếu','Định dạng phòng, sức chứa và tình trạng hoạt động.', 'projector'],
        ['showtimes.manage','admin.showtimes.index',[],'Suất chiếu','Ngày, giờ, phòng, giá vé và trạng thái suất chiếu.', 'calendar-clock'],
        ['showtimes.manage','admin.showtimes.schedule',[],'Lịch chiếu','Xem và sao chép lịch chiếu theo ngày.','calendar'],
        ['seats.manage','admin.seats.index',[],'Sơ đồ ghế','Bố trí ghế, lối đi, loại ghế và phụ thu.','seat'],
        ['tickets.manage','admin.tickets.index',[],'Vé & check-in','Tra cứu vé, mã QR và quản lý trạng thái.','ticket'],
    ],
    'Ưu đãi & dịch vụ' => [
        ['promotions.manage','admin.promotions.index',[],'Khuyến mãi','Mã, mức giảm, ảnh, thời hạn và lượt sử dụng.', 'tags'],
        ['promotions.manage','admin.promotions.display',[],'Banner khuyến mãi','Kiểu hiển thị, tự chạy ngang và thời gian chuyển.','image'],
        ['foods.manage','admin.foods.index',[],'Đồ ăn & thức uống','Sản phẩm, combo, giá bán, ảnh và tồn kho.','bag'],
        ['membership.manage','admin.membership.index',[],'Cấp bậc & quyền lợi','Hạng, mốc Stars, mức giảm và quyền lợi thành viên.', 'crown'],
        ['membership.manage','admin.membership.settings',[],'Chương trình thành viên','Quy tắc tích Stars, nội dung và chính sách.','settings'],
        ['gifts.manage','admin.gifts.index',[],'Quà tặng thành viên','Quà dạng voucher, ảnh, điều kiện Stars, hạn dùng và số lượt.','gift'],
    ],
    'Tài khoản & phân quyền' => [
        ['users.view','admin.users.index',['role'=>'customer'],'Khách hàng','Thông tin, lịch sử và trạng thái tài khoản.','users'],
        ['users.view','admin.users.index',['role'=>'staff'],'Nhân viên','Tài khoản nhân viên và quyền được cấp.','users'],
        ['users.view','admin.users.index',['role'=>'manager'],'Quản lý rạp','Tài khoản quản lý và quyền vận hành.','users'],
        ['membership.manage','admin.membership.members',[],'Thành viên & Stars','Theo dõi hạng, lịch sử và điều chỉnh điểm.','users'],
        ['permissions.manage','admin.permissions.index',[],'Cấp quyền admin','Quyền theo vai trò và từng tài khoản quản trị.', 'shield-check'],
    ],
];
@endphp
<div class="content-settings website-manager"><div class="settings-heading"><div><span class="settings-kicker">TRUNG TÂM QUẢN TRỊ</span><h1>Quản lý trang khách hàng</h1><p>Chọn khu vực để thay đổi nội dung hoặc quản lý hoạt động của website.</p></div><a class="settings-button" href="{{ route('home') }}" target="_blank" rel="noopener">Xem website @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a></div>
@foreach($groups as $title=>$items)
@php $items=collect($items)->filter(fn($item)=>auth()->user()->hasAdminPermission($item[0])); @endphp
@continue($items->isEmpty())
<section class="website-manager-section"><h2>{{ $title }}</h2><div class="website-manager-grid">@foreach($items as [$permission,$route,$parameters,$name,$description,$icon])<a class="website-manager-card" href="{{ route($route,$parameters) }}"><span class="website-manager-icon">@include('admin.partials.icon',['name'=>$icon,'size'=>24,'classes'=>''])</span><div><h3>{{ $name }}</h3><p>{{ $description }}</p></div><span class="website-manager-arrow" aria-hidden="true">@include('admin.partials.icon', ['name'=>'external-link', 'size'=>18])</span></a>@endforeach</div></section>@endforeach
</div>
@endsection
