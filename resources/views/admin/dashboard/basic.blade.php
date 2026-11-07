@extends('admin.layouts.app')
@section('title', 'Bảng điều khiển nhân viên')

@section('content')
<section class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <h1 class="text-2xl font-bold">Vận hành vé & check-in</h1>
    <p>Tra cứu vé, kiểm tra thanh toán và đón khách vào rạp.</p>
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.tickets.index') }}" class="bg-blue-600 text-white font-semibold px-4 py-3 rounded-lg">Tra cứu vé & check-in</a>
        <a href="{{ route('admin.tickets.create') }}" class="border border-slate-300 font-semibold px-4 py-3 rounded-lg">Tạo vé tại quầy</a>
    </div>
</section>
@endsection
