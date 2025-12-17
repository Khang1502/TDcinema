<div class="space-y-4">
    <div>
        <label class="block text-sm text-slate-300 mb-1">Tên khuyến mãi</label>
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
            <p class="text-xs text-slate-500 mt-1">Nếu loại % thì nhập 5 = 5%; nếu trực tiếp thì nhập số tiền.</p>
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
            <label class="block text-sm text-slate-300 mb-1">Giới hạn lượt sử dụng</label>
            <input type="number" name="usage_limit" min="0" value="{{ old('usage_limit', $promotion->usage_limit ?? '') }}" class="promo-input" placeholder="Bỏ trống nếu không giới hạn">
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
            <select name="status" class="promo-select" required>
                <option value="active" {{ old('status', $promotion->status ?? 'active') === 'active' ? 'selected' : '' }}>Đang hoạt động</option>
                <option value="expired" {{ old('status', $promotion->status ?? 'active') === 'expired' ? 'selected' : '' }}>Hết hạn</option>
            </select>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Thumbnail</label>
            <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp" class="promo-input">
            <p class="text-xs text-slate-500 mt-1">Ảnh sẽ hiển thị ở trang khuyến mãi (jpg, png, webp, tối đa 2MB).</p>
            @error('thumbnail') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        @isset($promotion)
            @if(!empty($promotion->thumbnail))
                <div>
                    <label class="block text-sm text-slate-300 mb-1">Ảnh hiện tại</label>
                    <img src="{{ asset('storage/' . $promotion->thumbnail) }}" alt="Thumbnail" class="rounded-lg border border-slate-700 max-h-32">
                </div>
            @endif
        @endisset
    </div>
</div>
