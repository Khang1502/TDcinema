@extends('admin.layouts.app')
@section('title', 'Chỉnh phương thức thanh toán')
@include('admin.payment-methods._assets')
@section('content')
<div class="ad-page payment-page">
    <header class="ad-heading"><div><span class="ad-kicker">TD CINEMA · {{ strtoupper($method->code) }}</span><h1>Chỉnh sửa {{ $method->name }}</h1><p>Nội dung được dùng tại bước thanh toán và khi tra cứu giao dịch.</p></div><a class="ad-button" href="{{ route('admin.payment-methods.index') }}">@include('admin.partials.icon', ['name'=>'arrow-left', 'size'=>17]) Danh sách phương thức</a></header>
    @if($errors->any())<div class="pay-errors" data-inline-notification role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="pm-editor-grid">
        <form class="ad-panel pm-editor" method="POST" action="{{ route('admin.payment-methods.update', $method) }}">
            @csrf @method('PUT')
            <div class="pay-card-heading"><h2>@include('admin.partials.icon', ['name'=>'pencil', 'size'=>19]) Nội dung hiển thị</h2></div>
            <label class="pay-field"><span>Tên phương thức <b>*</b></span><input type="text" name="name" value="{{ old('name', $method->name) }}" maxlength="100" required autocomplete="off">@error('name')<small class="pm-field-error">{{ $message }}</small>@enderror</label>
            <label class="pay-field"><span>Mô tả ngắn</span><textarea name="description" rows="3" maxlength="500" placeholder="Các loại thẻ hoặc cách thanh toán được hỗ trợ…">{{ old('description', $method->description) }}</textarea><small>Hiển thị dưới tên phương thức ở trang thanh toán.</small></label>
            <label class="pay-field"><span>Hướng dẫn thanh toán</span><textarea name="instructions" rows="5" maxlength="2000" placeholder="Nhập hướng dẫn hoặc lưu ý dành cho khách hàng…">{{ old('instructions', $method->instructions) }}</textarea><small>Có thể xuống dòng để chia các bước hướng dẫn.</small></label>
            <div class="pm-editor-row"><label class="pay-field"><span>Nhãn nổi bật</span><input type="text" name="badge" value="{{ old('badge', $method->badge) }}" maxlength="40" placeholder="Ví dụ: Nhanh chóng"><small>Để trống nếu không cần nhãn.</small></label><label class="pay-field"><span>Thứ tự hiển thị <b>*</b></span><input type="number" name="sort_order" value="{{ old('sort_order', $method->sort_order) }}" min="0" max="9999" required><small>Số nhỏ hiển thị trước.</small></label></div>
            <label class="pm-enabled"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $method->is_enabled))><span><strong>Cho phép sử dụng phương thức này</strong><small>{{ $method->is_online ? 'Khách có thể chọn tại bước thanh toán trực tuyến.' : 'Nhân viên có thể tạo vé và thu tiền mặt tại quầy.' }}</small></span></label>
            <footer class="pm-save-actions"><a class="ad-button" href="{{ route('admin.payment-methods.index') }}">Hủy</a><button class="ad-button primary" type="submit">@include('admin.partials.icon', ['name'=>'save', 'size'=>17]) Lưu thay đổi</button></footer>
        </form>
        <aside class="ad-panel pm-context">
            <span class="pm-brand" data-method="{{ $method->code }}" aria-hidden="true">{{ $method->monogram() }}</span><h2>{{ $method->name }}</h2>
            <dl class="pay-summary-list"><div><dt>Mã phương thức</dt><dd><code>{{ $method->code }}</code></dd></div><div><dt>Phạm vi áp dụng</dt><dd>{{ $method->is_online ? 'Thanh toán trực tuyến' : 'Tạo vé tại quầy' }}</dd></div><div><dt>Luồng xử lý hiện tại</dt><dd>{{ !$method->is_online ? 'Thu tiền tại quầy' : ($method->isDemo() ? 'Thanh toán mô phỏng' : 'Cổng MoMo') }}</dd></div></dl>
            <p>{{ $method->is_online ? 'Tên, mô tả, hướng dẫn và nhãn được cập nhật ở trang thanh toán sau khi lưu.' : 'Phương thức tại quầy không xuất hiện trong danh sách thanh toán trực tuyến.' }}</p>
            @if($method->is_online)<p>{{ $method->isDemo() ? 'Chế độ mô phỏng không trừ tiền của khách. Nội dung hiển thị không thay đổi luồng xử lý giao dịch.' : 'Kết nối MoMo sử dụng cấu hình tích hợp của hệ thống. Nội dung hiển thị không thay đổi thông tin kết nối.' }}</p>@endif
            <p>Lịch sử giao dịch được giữ khi đổi tên hoặc tạm dừng. Mã phương thức được giữ cố định để liên kết dữ liệu.</p>
        </aside>
    </div>
</div>
@endsection
