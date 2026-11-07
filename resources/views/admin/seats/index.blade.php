@extends('admin.layouts.app')
@section('title','Thiết kế sơ đồ ghế')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin/seats.css') }}?v={{ filemtime(public_path('assets/css/admin/seats.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/admin/seat-designer.css') }}?v={{ filemtime(public_path('assets/css/admin/seat-designer.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('assets/js/admin/seats.js') }}?v={{ filemtime(public_path('assets/js/admin/seats.js')) }}" defer></script>
<script src="{{ asset('assets/js/admin/seat-designer.js') }}?v={{ filemtime(public_path('assets/js/admin/seat-designer.js')) }}" defer></script>
@endpush
@section('content')
@foreach(['seat_layout_skipped'=>'Các phòng được giữ nguyên để bảo vệ đặt vé:','seat_price_skipped'=>'Các phòng chưa đổi giá vì đang có khách giữ ghế:'] as $key=>$label)
@if(session($key))<div class="seat-layout-notice" role="status"><strong>{{ $label }}</strong><ul>@foreach(session($key) as $room)<li>{{ $room }}</li>@endforeach</ul></div>@endif
@endforeach
@if($defaultRoomId) @include('admin.seats._designer')
@else <div class="seat-empty-state"><h2>Chưa có phòng chiếu</h2><p>Thêm phòng chiếu để bắt đầu thiết kế sơ đồ ghế.</p><a href="{{ route('admin.rooms.create') }}">Thêm phòng chiếu</a></div> @endif
@endsection
