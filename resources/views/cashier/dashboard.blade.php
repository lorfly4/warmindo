@extends('layouts.app')

@section('title', 'Kasir - '.config('app.name'))
@section('header', 'Kasir')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Kasir</h1>
        <p class="mt-1 text-sm text-gray-600">Buat transaksi baru atau lihat daftar transaksi yang sudah tercatat.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('cashier.orders') }}" class="card p-6 transition hover:border-brand-300">
            <h2 class="text-lg font-semibold text-gray-900">Transaksi</h2>
            <p class="mt-2 text-sm text-gray-600">Lihat produk, jumlah, status, dan total setiap transaksi.</p>
        </a>
        @can('order.create')
            <a href="{{ route('cashier.orders.create') }}" class="card p-6 transition hover:border-brand-300">
                <h2 class="text-lg font-semibold text-gray-900">Buat Transaksi</h2>
                <p class="mt-2 text-sm text-gray-600">Pilih produk dan jumlah yang dibeli.</p>
            </a>
        @endcan
    </div>
@endsection