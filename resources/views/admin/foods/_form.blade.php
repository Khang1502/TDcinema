<div class="space-y-4">
    <div>
        <label class="block text-sm text-slate-300 mb-1">Tên dịch vụ</label>
        <input type="text" name="name" value="{{ old('name', $food->name ?? '') }}" class="svc-input" required>
    </div>
    <div>
        <label class="block text-sm text-slate-300 mb-1">Mô tả</label>
        <textarea name="description" class="svc-input" rows="3" placeholder="Mô tả ngắn...">{{ old('description', $food->description ?? '') }}</textarea>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Giá (VND)</label>
            <input type="number" step="1000" min="0" name="price" value="{{ old('price', $food->price ?? 0) }}" class="svc-input" required>
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Trạng thái</label>
            <select name="status" class="svc-select" required>
                <option value="available" {{ old('status', $food->status ?? 'available') === 'available' ? 'selected' : '' }}>Hiện có</option>
                <option value="unavailable" {{ old('status', $food->status ?? 'available') === 'unavailable' ? 'selected' : '' }}>Ngừng cung cấp</option>
            </select>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4 items-center">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Ảnh</label>
            <input type="file" name="image" accept="image/*" class="svc-input">
            <p class="text-xs text-slate-500 mt-1">Tối đa 2MB. Bỏ trống nếu không đổi.</p>
        </div>
        @if(isset($food) && $food->image)
            <div class="flex items-center gap-3">
                <div class="text-sm text-slate-400">Ảnh hiện tại:</div>
                <img src="{{ asset('storage/'.$food->image) }}" alt="{{ $food->name }}" class="w-20 h-20 object-cover rounded-md border border-slate-700">
            </div>
        @endif
    </div>
</div>
