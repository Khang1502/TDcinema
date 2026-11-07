@extends('admin.layouts.app')
@section('title',$level->exists ? 'Chỉnh sửa cấp bậc' : 'Thêm cấp bậc')
@section('content')
<div class="ma-shell le-page">
    @include('admin.membership._header',['title'=>$level->exists ? 'Chỉnh sửa cấp bậc' : 'Thêm cấp bậc thành viên'])
    @push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/level-editor.css') }}?v={{ filemtime(public_path('assets/css/admin/level-editor.css')) }}">@endpush
    @push('scripts')<script src="{{ asset('assets/js/admin/level-editor.js') }}?v={{ filemtime(public_path('assets/js/admin/level-editor.js')) }}" defer></script>@endpush
    @php
        $editorColor=old('color',$level->color ?? 'blue');
        if (!is_string($editorColor) || !array_key_exists($editorColor,\App\Models\MembershipLevel::COLORS)) $editorColor='blue';
        $editorBenefits=old('benefits_text',implode("\n",$level->benefits ?? []));
        $editorBenefitLines=array_values(array_filter(array_map('trim',preg_split('/\R/',$editorBenefits ?? ''))));
        $editorActive=(string)old('is_active',$level->is_active ? '1' : '0');
        $colorUsage=$existingLevels->groupBy('color');
        $ownerLabel=fn ($owner)=>$owner->name.($owner->id === $level->id ? ' (hạng này)' : '').(!$owner->is_active ? ' (đang tắt)' : '');
        $usedColorCount=collect(array_keys(\App\Models\MembershipLevel::COLORS))->filter(fn ($color)=>$colorUsage->has($color))->count();
    @endphp
    <form method="POST" action="{{ $level->exists ? route('admin.membership.levels.update',$level) : route('admin.membership.levels.store') }}" data-level-editor data-level-id="{{ $level->id }}">
        @csrf @if($level->exists) @method('PUT') @endif
        <script type="application/json" data-level-thresholds>@json($existingLevels)</script>
        <div class="le-layout">
            <div class="le-sections">
                <section class="le-section" aria-labelledby="level-identity-title">
                    <header><span class="le-step">01</span><div><h2 id="level-identity-title">Tên & nhận diện</h2><p>Thông tin xuất hiện trên thẻ và trang thành viên.</p></div></header>
                    <label for="level-name">Tên cấp bậc <b>*</b></label><input id="level-name" name="name" value="{{ old('name',$level->name) }}" maxlength="50" required placeholder="Ví dụ: Thành viên bạch kim" @error('name') aria-invalid="true" aria-describedby="level-name-error" @enderror>
                    @error('name')<p class="le-error" id="level-name-error">{{ $message }}</p>@enderror
                    <label for="level-description">Giới thiệu ngắn</label><textarea id="level-description" name="description" rows="2" maxlength="500" placeholder="Giới thiệu điểm nổi bật của cấp bậc này..." @error('description') aria-invalid="true" @enderror>{{ old('description',$level->description) }}</textarea>
                    @error('description')<p class="le-error">{{ $message }}</p>@enderror
                    <fieldset class="le-palette"><legend>Màu nhận diện <b>*</b><span>{{ count(\App\Models\MembershipLevel::COLORS) }} màu · {{ $usedColorCount }} đã sử dụng</span></legend>
                        <div>@foreach(\App\Models\MembershipLevel::COLORS as $color=>$label)
                        @php
                            $owners=$colorUsage->get($color,collect());
                            $usageNote=$owners->isNotEmpty() ? 'Đã dùng: '.$owners->map($ownerLabel)->implode(', ') : 'Chưa sử dụng';
                        @endphp
                        <label class="le-color {{ $color }} {{ $owners->isNotEmpty() ? 'is-used' : '' }}" title="{{ $usageNote }}" data-color-option="{{ $color }}"><input type="radio" name="color" value="{{ $color }}" data-color-label="{{ $label }}" aria-describedby="level-color-note-{{ $color }}" @checked($editorColor === $color) required><span class="le-color-dot"></span><span class="le-color-copy"><strong>{{ $label }}</strong><small id="level-color-note-{{ $color }}">{{ $usageNote }}</small></span>@include('admin.partials.icon',['name'=>'check','size'=>14])</label>
                        @endforeach</div>
                        <p class="le-help">Ghi chú dựa trên các cấp bậc đã lưu, gồm cả hạng đang tắt.</p>
                        <p class="le-color-selection-note" data-level-color-usage aria-live="polite">{{ \App\Models\MembershipLevel::COLORS[$editorColor] }} · {{ $colorUsage->has($editorColor) ? 'Đã dùng: '.$colorUsage[$editorColor]->map($ownerLabel)->implode(', ') : 'Chưa sử dụng' }}</p>
                    </fieldset>
                    @error('color')<p class="le-error">{{ $message }}</p>@enderror
                </section>
                <section class="le-section" aria-labelledby="level-rules-title">
                    <header><span class="le-step">02</span><div><h2 id="level-rules-title">Điều kiện & giảm giá</h2><p>Hạng được xét theo số Stars của thành viên.</p></div></header>
                    <div class="le-fields"><div><label for="level-points">Mốc Stars tối thiểu <b>*</b></label><div class="le-unit-input"><input id="level-points" type="number" name="min_points" min="0" max="100000000" step="1" value="{{ old('min_points',$level->min_points) }}" required placeholder="Ví dụ: 15000" aria-describedby="level-points-help" @error('min_points') aria-invalid="true" @enderror><span>Stars</span></div><p class="le-help" id="level-points-help">Mốc 0 dành cho hạng khởi đầu. Mỗi hạng có một mốc riêng.</p>@error('min_points')<p class="le-error">{{ $message }}</p>@enderror<p class="le-error" data-level-points-error hidden></p></div>
                    <div><label for="level-discount">Giảm giá theo hạng <b>*</b></label><div class="le-unit-input"><input id="level-discount" type="number" name="discount_percent" min="0" max="100" step="1" value="{{ old('discount_percent',$level->discount_percent ?? 0) }}" required aria-describedby="level-discount-help" @error('discount_percent') aria-invalid="true" @enderror><span>%</span></div><p class="le-help" id="level-discount-help">Tự áp dụng khi khách không chọn mã khuyến mãi.</p>@error('discount_percent')<p class="le-error">{{ $message }}</p>@enderror</div></div>
                    <fieldset class="le-visibility"><legend>Trạng thái cấp bậc</legend><div><label><input type="radio" name="is_active" value="1" @checked($editorActive === '1') required>@include('admin.partials.icon',['name'=>'circle-check','size'=>18])<span><strong>Đang bật</strong><small>Hiển thị và dùng để xét hạng</small></span></label><label><input type="radio" name="is_active" value="0" @checked($editorActive === '0')>@include('admin.partials.icon',['name'=>'circle-x','size'=>18])<span><strong>Đang tắt</strong><small>Không xét hạng hoặc giảm giá</small></span></label></div></fieldset>
                    @error('is_active')<p class="le-error">{{ $message }}</p>@enderror
                </section>
                <section class="le-section" aria-labelledby="level-benefits-title">
                    <header><span class="le-step">03</span><div><h2 id="level-benefits-title">Quyền lợi bổ sung</h2><p>Nội dung khách hàng đọc khi tìm hiểu cấp bậc.</p></div></header>
                    <label for="level-benefits">Danh sách quyền lợi <span class="le-counter" data-level-benefit-count>{{ count($editorBenefitLines) }}/12 quyền lợi</span></label>
                    <textarea id="level-benefits" name="benefits_text" rows="6" maxlength="2000" aria-describedby="level-benefits-help" placeholder="Ví dụ:&#10;Quà sinh nhật tại rạp&#10;Ưu tiên hỗ trợ thành viên" @error('benefits_text') aria-invalid="true" @enderror>{{ $editorBenefits }}</textarea>
                    <p class="le-help" id="level-benefits-help">Mỗi quyền lợi một dòng. Tối đa 12 dòng, mỗi dòng không quá 160 ký tự.</p>@error('benefits_text')<p class="le-error">{{ $message }}</p>@enderror<p class="le-error" data-level-benefit-error hidden></p>
                    <p class="le-note">@include('admin.partials.icon',['name'=>'gift','size'=>18])<span>Voucher và quà tặng có mã được quản lý riêng tại @if(auth()->user()->hasAdminPermission('gifts.manage'))<a href="{{ route('admin.gifts.index') }}">Quà thành viên</a>@else mục Quà thành viên @endif.</span></p>
                </section>
            </div>
            <aside class="le-sidebar" aria-label="Xem trước cấp bậc">
                <div class="le-preview-panel"><div class="le-preview-heading"><span>XEM TRƯỚC THẺ</span><small>Cập nhật khi nhập</small></div>
                    <div class="le-card {{ $editorColor }}" data-level-card><div class="le-card-top"><span>{{ $programName }}</span><span>TD CINEMA</span></div><span class="le-card-emblem">@include('admin.partials.icon',['name'=>'crown','size'=>33])</span><h2 data-level-preview-name>{{ old('name',$level->name) ?: 'Tên cấp bậc' }}</h2><p data-level-preview-description>{{ old('description',$level->description) ?: 'Đặc quyền dành cho thành viên' }}</p><div class="le-card-bottom"><span data-level-preview-points>{{ number_format((int)old('min_points',$level->min_points),0,',','.') }} Stars</span><strong data-level-preview-discount>{{ old('discount_percent',$level->discount_percent ?? 0) }}% <small>ưu đãi</small></strong></div></div>
                    <div class="le-preview-status"><span data-level-preview-status>{{ $editorActive === '1' ? 'Đang bật' : 'Đang tắt' }}</span><p data-level-preview-rule>{{ $editorActive === '1' ? 'Thành viên đạt mốc Stars sẽ được xét vào cấp bậc này.' : 'Hạng đang tắt, không dùng để xét hạng hoặc giảm giá.' }}</p></div>
                    <h3>Quyền lợi bổ sung</h3><ul class="le-preview-benefits" data-level-preview-benefits>@forelse($editorBenefitLines as $benefit)<li>@include('admin.partials.icon',['name'=>'check','size'=>14])<span>{{ $benefit }}</span></li>@empty<li class="is-empty">Thêm quyền lợi để xem nội dung tại đây.</li>@endforelse</ul>
                    <template data-level-benefit-template><li>@include('admin.partials.icon',['name'=>'check','size'=>14])<span></span></li></template>
                    <p class="le-preview-caption">Bản xem trước minh họa nội dung và màu nhận diện của hạng.</p>
                </div>
                <details class="le-existing"><summary><span>@include('admin.partials.icon',['name'=>'rows-3','size'=>17]) Các mốc Stars hiện có</span>@include('admin.partials.icon',['name'=>'chevron-down','size'=>15])</summary><ol>@forelse($existingLevels as $existing)<li class="{{ $existing->id === $level->id ? 'is-current' : '' }}"><span>{{ $existing->name }}@unless($existing->is_active)<small>Đang tắt</small>@endunless</span><strong>{{ number_format($existing->min_points,0,',','.') }}</strong></li>@empty<li>Chưa có cấp bậc. Bắt đầu với mốc 0 Stars.</li>@endforelse</ol></details>
            </aside>
        </div>
        <div class="le-save-bar"><span>@include('admin.partials.icon',['name'=>'info','size'=>16]) Nội dung được áp dụng sau khi lưu.</span><div><a class="ma-button" href="{{ route('admin.membership.index') }}">Hủy</a><button class="ma-button primary" type="submit">@include('admin.partials.icon',['name'=>'save','size'=>17]) {{ $level->exists ? 'Lưu thay đổi' : 'Tạo cấp bậc' }}</button></div></div>
    </form>
</div>
@endsection
