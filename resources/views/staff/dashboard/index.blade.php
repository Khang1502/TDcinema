@extends('admin.layouts.app')

@section('title', 'Dashboard Nhân viên')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Dashboard Nhân viên</h1>
    <p class="text-slate-300">Chào {{ Auth::user()->name }}, đây là trang dành cho nhân viên rạp.</p>
@endsection
