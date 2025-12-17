@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-semibold mb-4">Tài khoản của bạn</h1>
        <p>Chào {{ Auth::user()->name }}, đây là nơi xem lịch sử đặt vé, điểm tích lũy, thông tin cá nhân,...</p>
    </div>
@endsection
