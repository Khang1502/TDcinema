<div class="space-y-4">
    <div><label class="block text-sm text-slate-300 mb-1">Mô tả / điều kiện sử dụng</label><textarea name="description" maxlength="2000" rows="3" class="promo-input">{{ old('description', $promotion->description ?? '') }}</textarea></div>
    <div class="promo-form-purpose {{ $giftAdmin ? 'member' : '' }}"><strong>@include('admin.partials.icon',['name'=>$giftAdmin ? 'gift' : 'tags','size'=>18]) {{ $giftAdmin ? 'Voucher dành riêng cho thành viên' : 'Chương trình khuyến mãi chung' }}</strong><p>{{ $giftAdmin ? 'Xuất hiện trong Quà tặng thành viên và bước thanh toán khi khách đủ Stars. Điểm không bị trừ khi sử dụng.' : 'Dành cho khách đặt vé. Quản lý banner, mã giảm giá và thời gian áp dụng tại đây.' }}</p></div>
    @if($giftAdmin)
    @php $giftPoints=old('min_member_points',$promotion->min_member_points ?? $giftDefaultPoints ?? 0); @endphp
    <div class="gifts-tier-picker" data-gift-tier-picker>
        <label for="gift-tier-preset">Cấp bậc mở khóa quà</label>
        <select id="gift-tier-preset" data-gift-tier-preset aria-describedby="gift-tier-hint"><option value="" @selected(!$giftLevels->contains('min_points',$giftPoints))>Mốc Stars tùy chỉnh</option>@foreach($giftLevels as $giftLevel)<option value="{{ $giftLevel->min_points }}" data-tier-name="{{ $giftLevel->name }}" @selected((string)$giftPoints === (string)$giftLevel->min_points)>{{ $giftLevel->name }} · {{ number_format($giftLevel->min_points,0,',','.') }} Stars</option>@endforeach</select>
        <p id="gift-tier-hint" data-gift-tier-hint aria-live="polite">Chọn hạng để điền nhanh Stars tối thiểu. Khách đủ điểm, kể cả hạng cao hơn, có thể nhận quà.</p>
    </div>
    <div><label class="block text-sm text-slate-300 mb-1" for="gift-min-stars">Stars tối thiểu để nhận quà</label><input id="gift-min-stars" type="number" name="min_member_points" min="0" max="100000000" value="{{ $giftPoints }}" class="promo-input" data-gift-min-stars><p class="text-xs text-slate-500 mt-1">Có thể điều chỉnh mốc riêng. Nhập 0 để dành cho mọi thành viên; dùng voucher không trừ Stars.</p></div>
    @push('scripts')<script src="{{ asset('assets/js/admin/gifts.js') }}?v={{ filemtime(public_path('assets/js/admin/gifts.js')) }}" defer></script>@endpush
    @endif
    <div>
        <label class="block text-sm text-slate-300 mb-1">{{ $giftAdmin ? 'Tên quà tặng / voucher' : 'Tên khuyến mãi' }}</label>
        <input type="text" name="name" value="{{ old('name', $promotion->name ?? '') }}" class="promo-input" required>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Mã</label>
            <input type="text" name="code" value="{{ old('code', $promotion->code ?? '') }}" class="promo-input" required>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Loại</label>
            <select name="type" class="promo-select" required>
                <option value="percent" {{ old('type', $promotion->type ?? 'percent') === 'percent' ? 'selected' : '' }}>Giảm giá %</option>
                <option value="fixed" {{ old('type', $promotion->type ?? 'percent') === 'fixed' ? 'selected' : '' }}>Giảm giá trực tiếp</option>
            </select>
        </div>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Giá trị</label>
            <input type="number" name="value" min="1" value="{{ old('value', $promotion->value ?? 0) }}" class="promo-input" required>
            <p class="text-xs text-slate-500 mt-1">Giảm theo phần trăm: 1–100%. Giảm theo số tiền: nhập số đồng, ví dụ 20000.</p>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Ngày bắt đầu</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($promotion->start_date ?? null)->format('Y-m-d')) }}" class="promo-input" required>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Ngày kết thúc</label>
            <input type="date" name="end_date" value="{{ old('end_date', optional($promotion->end_date ?? null)->format('Y-m-d')) }}" class="promo-input" required>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Số lượt sử dụng còn lại</label>
            <input type="number" name="usage_limit" min="0" value="{{ old('usage_limit', $promotion->usage_limit ?? '') }}" class="promo-input" placeholder="Bỏ trống nếu không giới hạn">
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
            <select name="status" class="promo-select" required>
                <option value="active" {{ old('status', $promotion->status ?? 'active') === 'active' ? 'selected' : '' }}>Đang hoạt động</option>
                <option value="expired" {{ old('status', $promotion->status ?? 'active') === 'expired' ? 'selected' : '' }}>Tắt khuyến mãi</option>
            </select>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4 promo-image-manager" data-promo-image-manager>
        <div>
            <label class="block text-sm text-slate-300 mb-1">{{ $giftAdmin ? 'Ảnh quà tặng' : 'Ảnh khuyến mãi / banner' }}</label>
            <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp" class="admin-file-input" aria-label="Chọn ảnh khuyến mãi">
            <p class="text-xs text-slate-500 mt-1" data-promo-image-status>Chọn tệp để thay ảnh. Để trống để giữ ảnh đã lưu. JPG, PNG, WebP, tối đa 2MB.</p>
            @include('admin.partials.image-source', ['imagePath' => $promotion->thumbnail ?? null, 'imageUrl' => isset($promotion) ? $promotion->imageUrl() : null, 'sourceKey' => 'promotion'])
            @if(!empty($promotion->thumbnail))
            <label class="promo-remove-image"><input type="checkbox" name="remove_thumbnail" value="1" @checked(old('remove_thumbnail'))> Gỡ ảnh khi lưu</label>
            @endif
            @error('thumbnail') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        @php $currentImage = isset($promotion) ? $promotion->imageUrl() : null; @endphp
        <div class="promo-image-preview"><span data-promo-image-label>Ảnh đã lưu</span><img data-promo-image-preview @if($currentImage) src="{{ $currentImage }}" @else hidden @endif alt="Ảnh ưu đãi"><p data-promo-image-empty @if($currentImage) hidden @endif>Chưa có ảnh</p></div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        @unless($giftAdmin)<div><label class="block text-sm text-slate-300 mb-1">Hiển thị trên trang chủ</label><select name="show_on_home" class="promo-select"><option value="1" @selected(old('show_on_home', $promotion->show_on_home ?? true))>Hiện trong khuyến mãi / banner</option><option value="0" @selected(!old('show_on_home', $promotion->show_on_home ?? true))>Ẩn trên trang chủ</option></select><p class="text-xs text-slate-500 mt-1">Mã còn hiệu lực vẫn dùng được khi thanh toán.</p></div>@endunless
        <div><label class="block text-sm text-slate-300 mb-1">Thứ tự hiển thị</label><input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $promotion->sort_order ?? 0) }}" class="promo-input"><p class="text-xs text-slate-500 mt-1">Số nhỏ xuất hiện trước.</p></div>
    </div>
</div>
<p class="text-sm text-slate-400">{{ $giftAdmin ? 'Voucher còn hiệu lực được hiển thị riêng cho thành viên đủ điều kiện.' : 'Khuyến mãi còn hiệu lực xuất hiện ở trang khuyến mãi và bước thanh toán.' }} Lượt chỉ giảm sau khi khách thanh toán thành công.</p>
@push('scripts')<script src="{{ asset('assets/js/admin/promotions.js') }}?v={{ filemtime(public_path('assets/js/admin/promotions.js')) }}" defer></script>@endpush
