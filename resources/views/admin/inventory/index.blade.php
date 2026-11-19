@extends('layouts.app')

@section('title', 'Inventori - '.config('app.name'))
@section('header', 'Inventori')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Inventori</h1>
        <div class="flex flex-wrap items-center gap-2">
            @can('inventory.update')
                <a href="{{ route('admin.ingredients.index') }}" class="btn btn-secondary">Bahan Baku</a>
                <a href="{{ route('admin.inventory.stock-in.form') }}" class="btn btn-success">+ Stok Masuk</a>
                <a href="{{ route('admin.inventory.stock-out.form') }}" class="btn btn-secondary">Stok Keluar</a>
                <a href="{{ route('admin.inventory.transfer.form') }}" class="btn btn-secondary">Transfer</a>
            @endcan
            <a href="{{ route('admin.inventory.movements') }}" class="btn btn-secondary">Riwayat Pergerakan</a>
            @can('inventory.update')
                <a href="{{ route('admin.warehouses.index') }}" class="btn btn-secondary">Gudang</a>
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">Pemasok</a>
            @endcan
        </div>
    </div>

    @if ($warehouseLowStock->isNotEmpty())
        <div class="card mb-4 border-l-4 !border-l-red-500 p-4">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6a3 3 0 003 3h12a3 3 0 003-3V7a3 3 0 00-3-3H6a3 3 0 00-3 3zm0 6h18M3 16h6a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm11 0h6a1 1 0 011 1v2a1 1 0 01-1 1h-5a1 1 0 01-1-1v-2z"/></svg>
                <div class="text-sm">
                    <p class="font-semibold text-red-800">{{ $warehouseLowStock->count() }} item di bawah ambang minimum gudang.</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($warehouseLowStock as $item)
                            <li class="text-gray-700">
                                {{ $item->ingredient?->name }} — sisa
                                <span class="font-semibold text-red-600">{{ $item->quantity }}</span>
                                <span class="text-gray-500">{{ $item->unit }} ({{ $item->warehouse?->name }}, min {{ $item->min_threshold }})</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="flex flex-wrap items-end gap-3 border-b border-gray-200 px-5 py-4">
            <div>
                <label for="warehouse" class="label">Gudang</label>
                <select id="warehouse" name="warehouse" class="select w-48">
                    <option value="">Semua Gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('warehouse') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search" class="label">Cari</label>
                <input id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari bahan baku..." class="input w-52">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            @if (request('warehouse') || request('search'))
                <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table-w">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Bahan Baku</th>
                        <th class="px-5 py-3 font-medium">SKU</th>
                        <th class="px-5 py-3 font-medium">Gudang</th>
                        <th class="px-5 py-3 font-medium">Stok</th>
                        <th class="px-5 py-3 font-medium">Satuan</th>
                        <th class="px-5 py-3 font-medium">Min</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $item->ingredient?->name }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ $item->ingredient?->sku ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ $item->warehouse?->name }}</td>
                            <td class="px-5 py-3">
                                <span class="badge {{ $item->quantity <= 0 ? 'bg-red-100 text-red-700' : ($item->isLow() ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    {{ $item->quantity }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ $item->unit }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600">{{ $item->min_threshold }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">
                                Tidak ada item stok.
                                @if (! request('warehouse'))
                                    Belum ada item di dalam gudang.
                                    @can('inventory.update')
                                        Gunakan menu <strong>Stok Masuk</strong> untuk mencatat stok pertama.
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4">{{ $items->links() }}</div>
    </div>
@endsection