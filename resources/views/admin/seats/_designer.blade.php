@php
    $currentRoom = $rooms->firstWhere('id', (int)$defaultRoomId);
    $columnCount = max(1, (int)($seatRows->flatten(1)->max('seat_number') ?? 16));
    $takenColumns = $seatRows->flatten(1)->pluck('seat_number')->unique()->all();
    $existingAisles = $seatRows->isEmpty() ? '5,12' : implode(',', array_diff(range(1, $columnCount), $takenColumns));
    $copyRooms = $allRooms->reject(fn ($room) => $room->id == $defaultRoomId)->values();
    $copyCinemas = $cinemas->whereIn('id', $copyRooms->pluck('cinema_id')->unique());
    $copyRoomId = old('source_room_id', '');
    $copyCinemaId = $copyRooms->firstWhere('id', (int) $copyRoomId)?->cinema_id
        ?? ($copyCinemas->contains('id', (int) $defaultCinemaId) ? $defaultCinemaId : '');
    $visibleCopyRooms = $copyRooms->where('cinema_id', $copyCinemaId);
@endphp
<div id="seatDesigner" class="seat-designer seat-admin-shell"
     data-update-url="{{ route('admin.seats.bulk-update') }}"
     data-preview-url="{{ route('admin.seats.preview') }}"
     data-data-url="{{ route('admin.seats.data', $defaultRoomId) }}">
    <div class="designer-heading"><div><span class="designer-eyebrow">KHÔNG GIAN PHÒNG CHIẾU</span><h2>Thiết kế sơ đồ ghế</h2><p>Tạo bố cục, chọn ghế trên sơ đồ và chỉnh ngay tại đây.</p></div><a class="designer-button" href="{{ route('admin.seats.create', ['cinema_id'=>$defaultCinemaId, 'room_id'=>$defaultRoomId]) }}">@include('admin.partials.icon',['name'=>'plus']) Thêm ghế riêng</a></div>
    <form class="designer-context" method="GET" action="{{ route('admin.seats.index') }}">
        <label>Cụm rạp<select name="cinema_id" onchange="this.form.room_id.value='';this.form.submit()">@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected($cinema->id == $defaultCinemaId)>{{ $cinema->name }}</option>@endforeach</select></label>
        <label>Phòng chiếu<select name="room_id" onchange="this.form.submit()">@foreach($rooms as $room)<option value="{{ $room->id }}" @selected($room->id == $defaultRoomId)>{{ $room->name }} · {{ $room->type }}</option>@endforeach</select></label>
        <div class="designer-context-stat">@include('admin.partials.icon',['name'=>'armchair','size'=>24])<div><strong id="designerTotal">{{ $seatStats['total'] }}</strong><span>vị trí ghế đã lưu</span></div></div>
        <span class="designer-saved" id="designerSaveState">@include('admin.partials.icon',['name'=>'circle-check','size'=>16]) Đã lưu</span>
    </form>
    <div class="designer-tabs" role="tablist" aria-label="Công cụ sơ đồ ghế">
        @foreach(['edit'=>['mouse-pointer-2','Chỉnh ghế'],'generate'=>['rows-3','Tạo sơ đồ'],'prices'=>['wallet','Giá loại ghế'],'copy'=>['copy','Sao chép']] as $key=>[$icon,$label])
        <button type="button" role="tab" id="tab-{{ $key }}" data-designer-tab="{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $key==='edit' ? 'true' : 'false' }}">@include('admin.partials.icon',['name'=>$icon]) {{ $label }}</button>
        @endforeach
    </div>
    <div class="designer-workbench">
        <section class="designer-stage" aria-label="Sơ đồ ghế">
            <div class="designer-stage-top"><div><h3>{{ $currentRoom->name }}</h3><span id="designerMode">Sơ đồ hiện tại · bấm ghế hoặc tên hàng để chọn</span></div><div class="designer-zoom"><button type="button" data-zoom="out" aria-label="Thu nhỏ">@include('admin.partials.icon',['name'=>'zoom-out'])</button><output id="designerZoomValue">100%</output><button type="button" data-zoom="in" aria-label="Phóng to">@include('admin.partials.icon',['name'=>'zoom-in'])</button><button type="button" data-zoom="fit" title="Vừa khung" aria-label="Vừa khung">@include('admin.partials.icon',['name'=>'maximize'])</button></div></div>
            <div class="designer-map-scroll" id="designerViewport">
                <div id="designerCanvas">
                @if($seatRows->isNotEmpty()) @include('admin.seats._saved-map')
                @else <div id="savedAuditorium" class="designer-empty">@include('admin.partials.icon',['name'=>'armchair','size'=>40])<h3>Phòng chưa có sơ đồ ghế</h3><p>Chọn một mẫu để bắt đầu thiết kế.</p><button type="button" data-open-generator class="designer-button designer-button--primary">Tạo sơ đồ đầu tiên</button></div> @endif
                <div id="previewAuditorium" class="admin-auditorium" hidden><div class="admin-screen"><span>Màn hình</span></div><div class="seat-grid" id="previewSeatGrid"></div></div>
                </div>
            </div>
            <div class="designer-stage-bottom"><div class="seat-legend">@foreach(['normal'=>'Thường','vip'=>'VIP','couple'=>'Đôi','inactive'=>'Bảo trì'] as $type=>$label)<span class="seat-legend__item"><i class="legend-dot legend-dot--{{ $type }}"></i>{{ $label }}</span>@endforeach</div><span id="designerMapCount">{{ $seatStats['active'] }} đang hoạt động</span></div>
        </section>
        <aside class="designer-inspector">
            <section id="panel-edit" role="tabpanel" aria-labelledby="tab-edit" data-designer-panel="edit">
                <div class="designer-inspector-title">@include('admin.partials.icon',['name'=>'paintbrush'])<h3>Chỉnh ghế đã chọn</h3></div>
                <div class="designer-selection"><b id="seatSelectionCount">0</b><span>ghế đang chọn</span></div><p id="designerSelectedCodes" class="designer-muted" aria-live="polite">Chọn ghế trên sơ đồ để bắt đầu.</p>
                <div class="designer-selection-actions"><button type="button" id="designerSelectAll">Chọn tất cả</button><button type="button" id="designerClear">Bỏ chọn</button></div>
                <form id="designerEditForm">
                    <label>Loại ghế<select name="seat_type"><option value="">Giữ nguyên</option><option value="normal">Thường</option><option value="vip">VIP</option><option value="sweetbox">Đôi</option></select></label>
                    <label>Trạng thái<select name="status"><option value="">Giữ nguyên</option><option value="1">Hoạt động</option><option value="0">Bảo trì / tạm khóa</option></select></label>
                    <label>Phụ thu (VNĐ)<input name="extra_price" type="number" min="0" max="999999" step="1" placeholder="Để trống để giữ nguyên"></label>
                    <button type="submit" id="designerApply" class="designer-button designer-button--primary" disabled>@include('admin.partials.icon',['name'=>'save']) Lưu thay đổi ghế</button>
                </form>
                <div class="designer-tip">@include('admin.partials.icon',['name'=>'info','size'=>16])<p>Bấm tên hàng để chọn cả hàng. Giữ Shift và bấm hai ghế để chọn một dải; Esc để bỏ chọn.</p></div>
            </section>
            <section id="panel-generate" role="tabpanel" aria-labelledby="tab-generate" data-designer-panel="generate" hidden>
                <div class="designer-inspector-title">@include('admin.partials.icon',['name'=>'rows-3'])<h3>Tạo sơ đồ mới</h3></div><p class="designer-muted">Chọn mẫu, điều chỉnh và xem trước khi lưu.</p>
                <div class="designer-presets"><button type="button" data-preset="compact">Gọn <small>6 × 10</small></button><button type="button" data-preset="standard">Tiêu chuẩn <small>10 × 16</small></button><button type="button" data-preset="large">Lớn <small>12 × 22</small></button></div>
                <form id="designerGenerateForm" method="POST" action="{{ route('admin.seats.generate', $defaultRoomId) }}" data-confirm-submit="Thay sơ đồ hiện tại bằng bố cục đang xem trước?">
                    @csrf
                    <div class="designer-fields"><label>Số hàng<input name="rows_count" type="number" min="1" max="52" value="{{ old('rows_count', max(1,$seatRows->count() ?: 10)) }}" required></label><label>Số cột<input name="seats_per_row" type="number" min="1" max="100" value="{{ old('seats_per_row', $columnCount) }}" required></label></div>
                    <label>Cột dành cho lối đi<input name="aisles" value="{{ old('aisles',$existingAisles) }}" placeholder="Ví dụ: 5,12" pattern="\s*(\d+\s*(,\s*\d+\s*)*)?"></label><p class="designer-muted">Các cột này được chừa trống; số ghế giữ theo vị trí cột.</p>
                    <label>Loại ghế mặc định<select name="default_type"><option value="normal" @selected(old('default_type')==='normal')>Thường</option><option value="vip" @selected(old('default_type')==='vip')>VIP</option><option value="sweetbox" @selected(old('default_type')==='sweetbox')>Đôi</option></select></label>
                    <label>Phụ thu mặc định (VNĐ)<input name="default_fee" type="number" min="0" max="999999" step="1" value="{{ old('default_fee',0) }}" required></label>
                    <div class="designer-preview-status" id="designerPreviewStatus" role="status">Đang chuẩn bị bản xem trước…</div>
                    <button class="designer-button designer-button--primary" id="designerGenerateSave" type="submit" disabled>@include('admin.partials.icon',['name'=>'save']) Lưu sơ đồ mới</button>
                </form><p class="designer-muted">Phòng đã có vé hoặc đang có khách giữ ghế sẽ không được thay sơ đồ.</p>
            </section>
            <section id="panel-prices" role="tabpanel" aria-labelledby="tab-prices" data-designer-panel="prices" hidden><div class="designer-inspector-title">@include('admin.partials.icon',['name'=>'wallet'])<h3>Giá vé theo loại ghế</h3></div><p class="designer-muted">Đặt một mức phụ thu cho mỗi loại ghế trong phần bên dưới. Giá vé = giá suất chiếu + phụ thu.</p><div class="designer-tip">@include('admin.partials.icon',['name'=>'info'])<p>Giá của vé đã bán được giữ nguyên.</p></div></section>
            <section id="panel-copy" role="tabpanel" aria-labelledby="tab-copy" data-designer-panel="copy" hidden>
                <div class="designer-inspector-title">@include('admin.partials.icon',['name'=>'copy'])<h3>Sao chép sơ đồ</h3></div>
                <p class="designer-muted">Xem sơ đồ nguồn theo rạp và phòng trước khi sao chép.</p>
                <form id="designerCopyForm" method="POST" action="{{ route('admin.seats.copy',$defaultRoomId) }}" data-confirm-submit="Thay sơ đồ phòng này bằng sơ đồ của phòng nguồn?"
                      data-room-options="{{ $copyRooms->map(fn ($room) => ['id'=>$room->id, 'cinema_id'=>$room->cinema_id, 'name'=>$room->name, 'type'=>$room->type])->toJson() }}">
                    @csrf
                    <label for="designerCopyCinema">Cụm rạp nguồn</label>
                    <select id="designerCopyCinema" @disabled($copyCinemas->isEmpty())>
                        <option value="">{{ $copyCinemas->isEmpty() ? 'Chưa có phòng để sao chép' : 'Chọn cụm rạp' }}</option>
                        @foreach($copyCinemas as $cinema)
                            <option value="{{ $cinema->id }}" @selected($cinema->id == $copyCinemaId)>{{ $cinema->name }}</option>
                        @endforeach
                    </select>
                    <div class="designer-copy-room-label"><label for="designerCopySource">Phòng nguồn</label><span id="designerCopyRoomCount">{{ $visibleCopyRooms->count() }} phòng</span></div>
                    <select name="source_room_id" id="designerCopySource" data-url-template="{{ route('admin.seats.data', '__ROOM__') }}" required @disabled(!$copyCinemaId)>
                        <option value="">{{ $copyCinemaId ? 'Chọn phòng nguồn' : 'Chọn cụm rạp trước' }}</option>
                        @foreach($visibleCopyRooms as $room)
                            <option value="{{ $room->id }}" @selected($room->id == $copyRoomId)>{{ $room->name }} · {{ $room->type }}</option>
                        @endforeach
                    </select>
                    @error('source_room_id')<p class="designer-muted text-red-400">{{ $message }}</p>@enderror
                    <p id="designerCopyStatus" class="designer-preview-status" role="status">Chưa chọn phòng nguồn.</p>
                    <button type="submit" id="designerCopySave" class="designer-button designer-button--primary" disabled>@include('admin.partials.icon',['name'=>'copy']) Áp dụng cho phòng này</button>
                </form>
            </section>
        </aside>
    </div>
    <div id="designerPrices" hidden>@include('admin.seats._pricing')</div>
    <details class="designer-advanced"><summary>@include('admin.partials.icon',['name'=>'settings-2']) Công cụ nâng cao <span>Đồng bộ sức chứa · áp dụng nhiều phòng</span></summary><div class="designer-advanced-grid"><section><h3>Đồng bộ sức chứa</h3><p>Thực tế {{ $seatStats['total'] }} vị trí · sức chứa đã ghi nhận {{ $seatStats['room_total'] }}.</p><form method="POST" action="{{ route('admin.seats.sync-total',$defaultRoomId) }}">@csrf @method('PATCH')<button class="designer-button" type="submit">@include('admin.partials.icon',['name'=>'refresh-cw']) Đồng bộ tổng ghế</button></form></section><section><h3>Áp dụng sơ đồ ghế cho tất cả các rạp</h3><p>Dùng sơ đồ đã lưu của phòng này cho {{ max(0,$allRooms->count()-1) }} phòng còn lại. Các phòng có vé hoặc giữ ghế sẽ được bỏ qua.</p><form method="POST" action="{{ route('admin.seats.apply-all',$defaultRoomId) }}">@csrf<label class="designer-checkbox"><input type="checkbox" name="confirm_apply_all" value="1" required> Tôi đồng ý thay sơ đồ của các phòng đủ điều kiện.</label><button class="designer-button" type="submit" @disabled($seatStats['total']===0 || $allRooms->count()<2)>@include('admin.partials.icon',['name'=>'copy']) Áp dụng cho tất cả các rạp</button></form></section></div></details>
</div>
