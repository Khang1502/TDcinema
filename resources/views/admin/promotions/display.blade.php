@extends('admin.layouts.app')
@section('title', 'Hiển thị khuyến mãi trang chủ')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/content-settings.css') }}?v={{ filemtime(public_path('assets/css/admin/content-settings.css')) }}">@endpush
@section('content')
<div class="content-settings">
    <div class="settings-heading"><div><span class="settings-kicker">KHUYẾN MÃI TRANG CHỦ</span><h1>Ưu đãi theo cách của bạn</h1><p>Chọn kiểu hiển thị và nhịp chuyển phù hợp cho các khuyến mãi.</p></div><div class="settings-actions"><a class="settings-button" href="{{ route('admin.promotions.index') }}">@include('admin.partials.icon',['name'=>'arrow-left','size'=>16]) Danh sách</a><a class="settings-button" href="{{ route('home') }}#promotions" target="_blank" rel="noopener">Xem trang chủ @include('admin.partials.icon',['name'=>'external-link','size'=>16])</a></div></div>
    @if($errors->any())<div data-inline-notification class="settings-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('admin.promotions.display.update') }}">@csrf @method('PUT')
        <div class="settings-grid"><div>
            <section class="settings-panel"><h2>Nội dung & hiển thị</h2><p class="settings-help">Áp dụng cho khu vực khuyến mãi ở trang chủ.</p><div class="settings-fields">
                <label class="settings-field wide">Tiêu đề<input name="title" value="{{ old('title', $settings->title) }}" maxlength="120" required></label>
                <label class="settings-field wide">Dòng giới thiệu<input name="eyebrow" value="{{ old('eyebrow', $settings->eyebrow) }}" maxlength="120" required></label>
                <label class="settings-field wide">Trạng thái<select name="is_visible"><option value="1" @selected(old('is_visible', $settings->is_visible))>Hiển thị trên trang chủ</option><option value="0" @selected(!old('is_visible', $settings->is_visible))>Tạm ẩn khu vực này</option></select></label>
            </div></section>
            <section class="settings-panel"><h2>Kiểu trình bày</h2><p class="settings-help">Cả hai chế độ đều hỗ trợ kéo ngang và chuyển bằng nút.</p><div class="settings-mode">
                @foreach(['banner'=>['Banner nổi bật','Một ưu đãi lớn, ảnh rõ và nội dung bên cạnh.'],'cards'=>['Dãy thẻ ngang','Nhiều ưu đãi trên một hàng, gọn và dễ so sánh.']] as $mode=>$copy)
                <label><input type="radio" name="display_mode" value="{{ $mode }}" @checked(old('display_mode', $settings->display_mode)===$mode)><div class="settings-banner-demo {{ $mode }}" aria-hidden="true"><span></span><span></span>@if($mode==='cards')<span></span>@endif</div><strong>{{ $copy[0] }}</strong><small>{{ $copy[1] }}</small></label>
                @endforeach
            </div></section>
        </div><section class="settings-panel"><h2>Chuyển tự động</h2><p class="settings-help">Khách có thể tạm dừng để đọc nội dung hoặc sao chép mã.</p><div class="settings-fields">
            <label class="settings-field wide">Tự chạy<select name="autoplay"><option value="1" @selected(old('autoplay', $settings->autoplay))>Bật tự chuyển ngang</option><option value="0" @selected(!old('autoplay', $settings->autoplay))>Chỉ chuyển khi khách thao tác</option></select></label>
            <label class="settings-field wide">Thời gian mỗi lượt (giây)<input type="number" name="interval_seconds" min="3" max="20" value="{{ old('interval_seconds', $settings->interval_seconds) }}" required><small>Từ 3 đến 20 giây; khuyến nghị 5–7 giây.</small></label>
        </div><div class="settings-note">Tự dừng khi khách rê chuột, dùng bàn phím, thao tác với banner hoặc chuyển sang tab khác. Chỉ chạy khi khu vực này đang hiện trên màn hình.</div><hr><p class="settings-help">Trong từng khuyến mãi, bạn có thể thay ảnh, chỉnh thứ tự và chọn hiện hoặc ẩn trên trang chủ.</p></section></div>
        <div class="settings-save"><button class="settings-button primary" type="submit">Lưu thay đổi</button><span>Nội dung được cập nhật ngay trên website.</span></div>
    </form>
</div>
@endsection
