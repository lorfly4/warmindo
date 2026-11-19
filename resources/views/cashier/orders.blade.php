@extends('layouts.app')

@section('title', 'Transaksi - Kasir - '.config('app.name'))
@section('header', 'Transaksi')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Daftar Transaksi</h1>
        <div class="flex gap-2">
            <a href="{{ route('cashier.history') }}" class="btn btn-secondary">Riwayat</a>
            @can('order.create')
                <a href="{{ route('cashier.orders.create') }}" class="btn btn-primary">Buat Transaksi</a>
            @endcan
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-w">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Waktu</th>
                        <th class="px-4 py-3 font-medium">Produk Dibeli</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Pembayaran</th>
                        <th class="px-4 py-3 text-right font-medium">Total</th>
                        <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $order->order_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $order->ordered_at?->format('d M H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $order->items->map(fn ($item) => $item->product_name.' × '.$item->quantity)->join(', ') }}
                            </td>
                            <td class="px-4 py-3"><span class="badge bg-blue-100 text-blue-700">{{ \App\Models\Order::$flowLabels[$order->order_status] ?? $order->order_status }}</span></td>
                            <td class="px-4 py-3">
                                @if ($order->payments->contains(fn ($payment) => $payment->status === \App\Models\Payment::STATUS_PAID))
                                    <span class="badge bg-emerald-100 text-emerald-700">Lunas</span>
                                @else
                                    <span class="badge bg-amber-100 text-amber-700">{{ $order->payment_status === \App\Models\Order::PAYMENT_PENDING ? 'Belum Bayar' : $order->payment_status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('cashier.orders.show', $order) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection