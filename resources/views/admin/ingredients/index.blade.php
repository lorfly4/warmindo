@extends('layouts.app')

@section('title', 'Bahan Baku - ' . config('app.name'))
@section('header', 'Bahan Baku')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Bahan Baku</h1>
        <a href="{{ route('admin.ingredients.create') }}" class="btn btn-primary">Tambah Bahan Baku</a>
    </div>

    <form method="GET" action="{{ route('admin.ingredients.index') }}" class="mb-5 flex items-center gap-2">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode bahan..." class="input max-w-xs">
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if (request('search'))
            <a href="{{ route('admin.ingredients.index') }}" class="btn btn-secondary">Reset</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-w">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama Bahan</th>
                        <th class="px-4 py-3 font-medium">Kode</th>
                        <th class="px-4 py-3 font-medium">Item Gudang</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $ingredient->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->sku ?: '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $ingredient->inventory_items_count }}</td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $ingredient->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $ingredient->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.ingredients.edit', $ingredient) }}" class="btn btn-secondary !px-3 !py-1 text-xs">Edit</a>
                                    <form method="POST" action="{{ route('admin.ingredients.destroy', $ingredient) }}" onsubmit="return confirm('Yakin ingin menghapus bahan baku ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger !px-3 !py-1 text-xs">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada bahan baku.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $ingredients->links() }}</div>
@endsection