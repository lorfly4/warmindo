@extends('layouts.app')

@section('title', 'Buat Transaksi - Kasir - '.config('app.name'))
@section('header', 'Buat Transaksi')

@section('content')
    @php
        $selectedProductIds = old('product_ids', []);
        $selectedProductIds = is_array($selectedProductIds) ? array_map('intval', $selectedProductIds) : [];
        $quantities = old('quantities', []);
        $quantities = is_array($quantities) ? $quantities : [];
    @endphp

    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Buat Transaksi</h1>
            <p class="mt-1 text-sm text-gray-600">Pilih produk dan jumlah yang dibeli.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cashier.orders') }}" class="btn btn-secondary">Kembali ke Transaksi</a>
            <button type="submit" form="cashier-order-form" class="btn btn-primary" @disabled($products->isEmpty())>Buat Transaksi</button>
        </div>
    </div>

    <form
        id="cashier-order-form"
        method="POST"
        action="{{ route('cashier.orders.store') }}"
        class="space-y-5"
        x-data="{
            search: '',
            orderType: @js(old('order_type', 'take_away')),
            selectedProducts: @js($selectedProductIds),
            quantities: @js($quantities),
            toggleProduct(productId) {
                if (this.selectedProducts.includes(productId)) {
                    this.selectedProducts = this.selectedProducts.filter((selectedId) => selectedId !== productId);
                    return;
                }

                this.selectedProducts.push(productId);
                this.quantities[productId] = this.quantities[productId] || 1;
            },
            adjustQuantity(productId, amount, maxQuantity) {
                if (!this.selectedProducts.includes(productId)) {
                    if (amount > 0) {
                        this.toggleProduct(productId);
                    }

                    return;
                }

                const quantity = Number(this.quantities[productId] || 1) + amount;

                if (quantity < 1) {
                    this.toggleProduct(productId);
                    return;
                }

                this.quantities[productId] = Math.min(quantity, maxQuantity, 99);
            },
            visibleProductCount() {
                const query = this.search.trim().toLocaleLowerCase();

                return Array.from(this.$refs.productGrid.querySelectorAll('[data-product-name]'))
                    .filter((product) => product.dataset.productName.includes(query))
                    .length;
            }
        }"
    >
        @csrf

        @if ($errors->any())
            <div class="space-y-1 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <fieldset class="space-y-2">
            <legend class="label">Tipe Pesanan</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    class="card flex cursor-pointer items-center gap-3 p-4 transition hover:border-brand-500/70"
                    :class="orderType === 'take_away' ? 'border-brand-500 ring-2 ring-brand-500/40' : ''"
                >
                    <input
                        type="radio"
                        name="order_type"
                        value="take_away"
                        x-model="orderType"
                        @checked(old('order_type', 'take_away') === 'take_away')
                        class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Take Away</span>
                        <span class="block text-xs text-gray-500">Pesanan dibawa pulang</span>
                    </span>
                </label>
                <label
                    class="card flex cursor-pointer items-center gap-3 p-4 transition hover:border-brand-500/70"
                    :class="orderType === 'dine_in' ? 'border-brand-500 ring-2 ring-brand-500/40' : ''"
                >
                    <input
                        type="radio"
                        name="order_type"
                        value="dine_in"
                        x-model="orderType"
                        @checked(old('order_type') === 'dine_in')
                        class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Dine In</span>
                        <span class="block text-xs text-gray-500">Pesanan disajikan di meja</span>
                    </span>
                </label>
            </div>
            @error('order_type')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror
        </fieldset>

        <div x-cloak x-show="orderType === 'dine_in'" x-transition>
            <label for="table_id" class="label">Pilih Meja</label>
            <select
                id="table_id"
                name="table_id"
                class="select @error('table_id') border-red-400 @enderror"
                :required="orderType === 'dine_in'"
                :disabled="orderType !== 'dine_in'"
            >
                <option value="">Pilih meja yang tersedia</option>
                @foreach ($tables as $table)
                    <option value="{{ $table->id }}" @selected((string) old('table_id') === (string) $table->id)>
                        {{ $table->table_number }}{{ $table->name ? ' · '.$table->name : '' }}{{ $table->area ? ' · '.$table->area->name : '' }}
                    </option>
                @endforeach
            </select>
            @error('table_id')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
            @if ($tables->isEmpty())
                <p class="mt-1 text-sm text-gray-500">Belum ada meja tersedia. Atur meja terlebih dahulu untuk pesanan dine in.</p>
            @endif
        </div>

        <label for="product-search" class="sr-only">Cari produk</label>
        <input
            id="product-search"
            type="search"
            x-model="search"
            placeholder="Cari nama produk..."
            class="input"
            autocomplete="off"
        >

        <div x-ref="productGrid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @forelse ($products as $product)
                @php
                    $soldOut = $product->stock_type === 'limited' && $product->stock < 1;
                    $maxQuantity = $product->stock_type === 'limited' ? min((int) $product->stock, 99) : 99;
                @endphp
                <div
                    data-product-name="{{ strtolower($product->name) }}"
                    x-show="name.includes(search.trim().toLocaleLowerCase())"
                    x-data="{ name: @js(strtolower($product->name)) }"
                    class="card relative flex aspect-square min-w-0 flex-col p-3 transition {{ $soldOut ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-brand-500/70' }}"
                    @unless ($soldOut)
                        role="checkbox"
                        tabindex="0"
                        :aria-checked="selectedProducts.includes({{ $product->id }})"
                        :class="selectedProducts.includes({{ $product->id }}) ? 'border-brand-500 ring-2 ring-brand-500/40' : ''"
                        @click="toggleProduct({{ $product->id }})"
                        @keydown.enter.prevent="toggleProduct({{ $product->id }})"
                        @keydown.space.prevent="toggleProduct({{ $product->id }})"
                    @else
                        aria-disabled="true"
                    @endunless
                >
                    <div class="flex justify-end">
                        <input
                            id="product-{{ $product->id }}"
                            type="checkbox"
                            name="product_ids[]"
                            value="{{ $product->id }}"
                            :checked="selectedProducts.includes({{ $product->id }})"
                            @disabled($soldOut)
                            tabindex="-1"
                            aria-hidden="true"
                            @click.stop.prevent="toggleProduct({{ $product->id }})"
                            class="pointer-events-none h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                        >
                    </div>
                    <span class="pointer-events-none mt-2 line-clamp-2 min-h-10 text-sm font-medium leading-5 text-gray-900">
                        {{ $product->name }}
                    </span>
                    <div class="mt-auto">
                        <p class="truncate text-sm font-semibold text-brand-300">
                            Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                        </p>
                        <p class="truncate text-xs text-gray-500">
                            Stok: {{ $product->stock_type === 'limited' ? number_format($product->stock, 0, ',', '.') : 'Tidak terbatas' }}
                        </p>
                        <div class="mt-2 flex items-center justify-between gap-2" @click.stop @keydown.stop>
                            <button
                                type="button"
                                class="btn btn-secondary h-9 w-9 px-0 py-0"
                                aria-label="Kurangi jumlah {{ $product->name }}"
                                :disabled="{{ $soldOut ? 'true' : '!selectedProducts.includes('.$product->id.')' }}"
                                @click="adjustQuantity({{ $product->id }}, -1, {{ $maxQuantity }})"
                            >−</button>
                            <span class="min-w-6 text-center text-sm font-semibold" aria-live="polite" x-text="selectedProducts.includes({{ $product->id }}) ? (quantities[{{ $product->id }}] || 1) : 0"></span>
                            <input
                                type="hidden"
                                name="quantities[{{ $product->id }}]"
                                :value="quantities[{{ $product->id }}] || 1"
                            >
                            <button
                                type="button"
                                class="btn btn-secondary h-9 w-9 px-0 py-0"
                                aria-label="Tambah jumlah {{ $product->name }}"
                                @disabled($soldOut)
                                :disabled="{{ $soldOut ? 'true' : 'selectedProducts.includes('.$product->id.') && quantities['.$product->id.'] >= '.$maxQuantity.' ? true : false' }}"
                                @click="adjustQuantity({{ $product->id }}, 1, {{ $maxQuantity }})"
                            >+</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="card col-span-full p-6 text-center text-sm text-gray-500">Belum ada produk yang tersedia.</p>
            @endforelse
        </div>

        <p x-show="search.trim() && visibleProductCount() === 0" class="card p-6 text-center text-sm text-gray-500" role="status">Produk tidak ditemukan.</p>
    </form>
@endsection
