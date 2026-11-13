@extends('admin.layouts.app')
@section('title', 'Thêm phương thức thanh toán')
@include('admin.payment-methods._assets')
@section('content')
<div class="ad-page payment-page">
    <header class="ad-heading"><div><span class="ad-kicker">TD CINEMA · THANH TOÁN</span><h1>Thêm phương thức thanh toán</h1><p>Tạo lựa chọn trực tuyến mới cho luồng thanh toán mô phỏng.</p></div><a class="ad-button" href="{{ route('admin.payment-methods.index') }}">@include('admin.partials.icon', ['name'=>'arrow-left', 'size'=>17]) Danh sách phương thức</a></header>
    @if($errors->any())<div class="pay-errors" data-inline-notification role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="ad-panel pm-editor pm-create" method="POST" action="{{ route('admin.payment-methods.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="pay-card-heading"><h2>@include('admin.partials.icon', ['name'=>'plus', 'size'=>19]) Thông tin phương thức</h2></div>
        <div class="pm-editor-row"><label class="pay-field"><span>Tên phương thức <b>*</b></span><input type="text" name="name" value="{{ old('name') }}" maxlength="100" required autocomplete="off" placeholder="Ví dụ: Ví thanh toán mới">@error('name')<small class="pm-field-error">{{ $message }}</small>@enderror</label><label class="pay-field"><span>Mã phương thức <b>*</b></span><input type="text" name="code" value="{{ old('code') }}" maxlength="40" required pattern="[a-z][a-z0-9_]{2,39}" placeholder="vi_moi"><small>Chữ thường, số, dấu gạch dưới; bắt đầu bằng chữ, tối thiểu 3 ký tự. Không đổi được sau khi tạo.</small>@error('code')<small class="pm-field-error">{{ $message }}</small>@enderror</label></div>
        <label class="pay-field"><span>Ảnh phương thức</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG hoặc WebP, tối đa 2 MB. Nếu chưa có ảnh, hệ thống dùng ký hiệu thay thế.</small>@error('logo')<small class="pm-field-error">{{ $message }}</small>@enderror</label>
        <label class="pay-field"><span>Mô tả ngắn</span><textarea name="description" rows="3" maxlength="500" placeholder="Mô tả hiển thị ở bước thanh toán…">{{ old('description') }}</textarea></label>
        <label class="pay-field"><span>Hướng dẫn thanh toán</span><textarea name="instructions" rows="5" maxlength="2000" placeholder="Hướng dẫn dành cho khách hàng…">{{ old('instructions') }}</textarea></label>
        <div class="pm-editor-row"><label class="pay-field"><span>Nhãn nổi bật</span><input type="text" name="badge" value="{{ old('badge') }}" maxlength="40" placeholder="Ví dụ: Nhanh chóng"></label><label class="pay-field"><span>Thứ tự hiển thị <b>*</b></span><input type="number" name="sort_order" value="{{ old('sort_order', $nextOrder) }}" min="0" max="9999" required><small>Số nhỏ hiển thị trước.</small></label></div>
        <aside class="pm-notice">@include('admin.partials.icon', ['name'=>'info', 'size'=>19])<p>Phương thức mới được tạo ở trạng thái tạm dừng. Khi bật, hệ thống xử lý bằng luồng mô phỏng; việc thêm tên và ảnh không tạo kết nối với cổng thanh toán thật.</p></aside>
        <footer class="pm-save-actions"><a class="ad-button" href="{{ route('admin.payment-methods.index') }}">Hủy</a><button class="ad-button primary" type="submit">@include('admin.partials.icon', ['name'=>'plus', 'size'=>17]) Thêm phương thức</button></footer>
    </form>
</div>
@endsection
