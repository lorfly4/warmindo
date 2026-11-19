@extends('layouts.kiosk')

@section('title', 'Riwayat Pesanan')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase text-gold-700">Pesanan Anda</p>
                <h1 class="mt-1 font-display text-2xl font-semibold text-ink-900">Riwayat Pesanan</h1>
            </div>
            <a href="{{ route('menu.index') }}" class="btn btn-secondary">Pesan Lagi</a>
        </div>

        @if ($orders->isEmpty())
            <div class="card mt-6 px-6 py-12 text-center">
                <h2 class="font-display text-lg font-semibold text-ink-900">Belum ada pesanan</h2>
                <p class="mt-2 text-sm text-ink-500">Pesanan dari sesi ini akan muncul di sini.</p>
                <a href="{{ route('menu.index') }}" class="btn btn-primary mt-5">Lihat Menu</a>
            </div>
        @else
            <ul class="card mt-6 divide-y divide-ink-900/10">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('tracking.show', $order) }}" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 transition hover:bg-cream-100/70 sm:px-6">
                            <div>
                                <p class="font-semibold text-ink-900">{{ $order->order_number }}</p>
                                <p class="mt-1 text-sm text-ink-500">
                                    {{ $order->ordered_at?->format('d M Y, H:i') }} ·
                                    {{ \App\Models\Order::$flowLabels[$order->order_status] ?? ucwords($order->order_status) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-ink-900">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-forest-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection