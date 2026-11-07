@include('admin.partials.form-menu-assets')
<div class="am-field">
    <label class="label" for="cinema-city">Tỉnh/Thành phố</label>
    <div class="am-combobox" data-city-picker>
        <div class="am-city-control">@include('admin.partials.icon',['name'=>'building-2','size'=>18])
            <input id="cinema-city" name="city" value="{{ old('city', $cinema->city ?? '') }}" maxlength="255" class="input" placeholder="Chọn hoặc tìm tỉnh/thành phố..." role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="cinema-city-options" aria-describedby="cinema-city-help" autocomplete="off" @error('city') aria-invalid="true" @enderror>
            <button type="button" data-city-toggle aria-label="Mở danh sách tỉnh/thành phố" aria-controls="cinema-city-options" aria-expanded="false">@include('admin.partials.icon',['name'=>'chevron-down','size'=>17])</button>
        </div>
        <div class="am-menu am-city-menu" data-city-menu hidden>
            <div class="am-menu-heading">Chọn tỉnh/thành phố <span data-city-result-count>{{ $citySuggestions->count() }} gợi ý</span></div>
            <div class="am-options" role="listbox" id="cinema-city-options" aria-label="Tỉnh/thành phố">
                @foreach($citySuggestions as $city)<div class="am-city-option" role="option" id="cinema-city-option-{{ $loop->index }}" aria-selected="{{ old('city', $cinema->city ?? '') === $city ? 'true' : 'false' }}" data-city-option data-value="{{ $city }}" data-search="{{ $city }} {{ \App\Support\CinemaRegions::LABELS[\App\Support\CinemaRegions::infer($city)] ?? '' }}">@include('admin.partials.icon',['name'=>'building-2','size'=>16])<span>{{ $city }}</span>@include('admin.partials.icon',['name'=>'check','size'=>16])</div>@endforeach
            </div>
            <p class="am-menu-empty" data-city-empty hidden>Chưa có gợi ý phù hợp. Bạn vẫn có thể dùng tên vừa nhập.</p>
            <p class="am-menu-note">Gõ để tìm kiếm hoặc nhập tỉnh/thành phố mới.</p>
        </div>
    </div>
    <p class="am-field-help" id="cinema-city-help">Chọn từ danh sách hoặc nhập địa điểm mới.</p>
    @error('city')<p class="am-field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div>
    <label class="label" for="cinema-region">Khu vực hiển thị</label>
    <select id="cinema-region" name="region" class="input">
        <option value="">Tự động theo tỉnh/thành phố</option>
        @foreach($regionOptions as $key => $label)
            <option value="{{ $key }}" @selected(old('region', $cinema->region ?? '') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="location-hint">{{ isset($cinema) ? 'Hiện tại: '.$cinema->region_label.'. ' : '' }}Có thể chọn khu vực riêng để sắp xếp rạp trên website.</p>
</div>
