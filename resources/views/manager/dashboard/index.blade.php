@extends('admin.layouts.app')

@section('title', 'Dashboard Quản lý rạp')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Dashboard Quản lý rạp</h1>
    <p class="text-slate-300">Chào {{ Auth::user()->name }}, đây là trang dành cho quản lý rạp.</p>
@endsection
