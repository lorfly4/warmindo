@extends('layouts.kiosk')

@section('title', 'Pesan Menu - '.config('app.name'))

@section('content')
    @include('customer._cart-drawer', ['lines' => $drawerLines, 'subtotal' => $drawerSubtotal])

    <section class="mx-auto max-w-3xl text-center">
        <p class="eyebrow">Buat Pesanan</p>
        <h1 class="section-title mt-3">Pesan menu favorit Anda</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-ink-500 sm:text-base">
            Pilih satu atau beberapa menu, atur jumlahnya, lalu lanjutkan ke pembayaran.
        </p>
    </section>

    <div class="mx-auto mt-10 max-w-3xl pb-28">
        @forelse ($groups as $group)
            <section class="mb-8">
                <h2 class="mb-3 font-display text-lg font-semibold text-ink-900">{{ $group['name'] }}</h2>
                <ul class="divide-y divide-ink-900/10 overflow-hidden rounded-2xl border border-ink-900/10 bg-cream-50 shadow-sm">
                    @foreach ($group['products'] as $product)
                        @php $qty = $cartQuantities[$product->id] ?? 0; @endphp
                        <li class="flex items-center justify-between gap-4 px-4 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink-900">{{ $product->name }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="font-display text-base font-semibold text-forest-800">Rp {{ number_format($product->sale_price, 0, ',', '.') }}</span>
                                    @if ($weeklyPromo && $weeklyPromo->coversProduct($product->id, $product->category_id) && ! $product->isSoldOut())
                                        <span class="badge badge-gold">Diskon {{ (int) $weeklyPromo->value }}%</span>
                                    @endif
                                </div>
                            </div>

                            @if ($product->isSoldOut())
                                <span class="badge shrink-0 bg-burgundy-700 text-cream-50">Habis</span>
                            @else
                                <div class="shrink-0">
                                    <form method="POST" action="{{ route('cart.update', $product->id) }}"
                                          data-cart-ajax data-menu-stepper="{{ $product->id }}"
                                          class="flex items-center rounded-lg border border-ink-900/15 bg-cream-100/70"
                                          @if ($qty === 0) hidden @endif>
                                        @csrf
                                        <button type="submit" name="quantity" value="{{ max(1, $qty) - 1 }}" data-menu-minus
                                                aria-label="Kurangi {{ $product->name }}" class="px-3 py-1.5 text-sm font-semibold text-ink-500 transition hover:text-forest-700">−</button>
                                        <span class="min-w-7 text-center text-sm font-semibold text-ink-800" data-menu-qty="{{ $product->id }}" aria-label="{{ $qty }} di keranjang">{{ $qty }}</span>
                                        <button type="submit" name="quantity" value="{{ $qty + 1 }}" data-menu-plus
                                                @if ($product->isLimitedStock() && $qty >= $product->stock) disabled @endif
                                                aria-label="Tambah {{ $product->name }}" class="px-3 py-1.5 text-sm font-semibold text-ink-500 transition hover:text-forest-700 disabled:opacity-40">+</button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.add') }}" data-cart-ajax data-menu-add="{{ $product->id }}"
                                          class="flex items-center" @if ($qty > 0) hidden @endif>
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" aria-label="Tambah {{ $product->name }} ke keranjang" class="btn btn-primary btn-sm">+ Tambah</button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="empty-state">
                <p class="text-sm text-ink-500">Tidak ada menu tersedia saat ini.</p>
            </div>
        @endforelse
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-900/10 bg-cream-50/95 backdrop-blur">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-5 py-3 sm:px-8">
            <div>
                <p class="text-xs text-ink-500">Total <span x-text="$store.cartCount">{{ $cartCount }}</span> item</p>
                <p class="font-display text-lg font-semibold text-forest-800" data-cart-subtotal>Rp {{ number_format($drawerSubtotal, 0, ',', '.') }}</p>
            </div>
            <a href="{{ route('cart.index') }}" class="btn btn-primary">Lihat Pesanan</a>
        </div>
    </div>
@endsection
