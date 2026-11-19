@extends('layouts.app')

@section('title', 'Transfer Stok - '.config('app.name'))
@section('header', 'Transfer Stok')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Transfer Stok</h1>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">&larr; Kembali</a>
    </div>

    <form method="POST" action="{{ route('admin.inventory.transfer.store') }}" class="card p-6">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="ingredient_id" class="label">Bahan Baku</label>
                <select name="ingredient_id" id="ingredient_id" class="select @error('ingredient_id') border-red-400 @enderror" required>
                    <option value="">Pilih bahan baku...</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}" @selected(old('ingredient_id') == $ingredient->id)>{{ $ingredient->name }}{{ $ingredient->sku ? ' ('.$ingredient->sku.')' : '' }}</option>
                    @endforeach
                </select>
                @error('ingredient_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="from_warehouse_id" class="label">Gudang Asal</label>
                <select name="from_warehouse_id" id="from_warehouse_id" class="select @error('from_warehouse_id') border-red-400 @enderror" required>
                    <option value="">Pilih gudang asal...</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('from_warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('from_warehouse_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="to_warehouse_id" class="label">Gudang Tujuan</label>
                <select name="to_warehouse_id" id="to_warehouse_id" class="select @error('to_warehouse_id') border-red-400 @enderror" required>
                    <option value="">Pilih gudang tujuan...</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('to_warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('to_warehouse_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="quantity" class="label">Jumlah</label>
                <input type="number" name="quantity" id="quantity" min="1" value="{{ old('quantity', 1) }}" class="input @error('quantity') border-red-400 @enderror" required>
                @error('quantity')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="unit" class="label">Satuan</label>
                <select name="unit" id="unit" class="select @error('unit') border-red-400 @enderror" required>
                    @foreach (['pcs', 'kg', 'g', 'ml', 'L'] as $unit)
                        <option value="{{ $unit }}" @selected(old('unit', 'pcs') === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
                @error('unit')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="note" class="label">Catatan (opsional)</label>
                <input type="text" name="note" id="note" maxlength="500" value="{{ old('note') }}" class="input @error('note') border-red-400 @enderror">
                @error('note')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button type="submit" class="btn btn-primary">Transfer Stok</button>
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection