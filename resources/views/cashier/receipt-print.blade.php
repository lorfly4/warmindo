<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->order_number }}</title>
    <script>
        window.addEventListener('DOMContentLoaded', () => setTimeout(() => window.print(), 250));
    </script>
    <style>
        /* Kertas thermal 58mm. Lebar area cetak = 58mm - 2 x 4mm = 50mm */
        @page { size: 58mm auto; margin: 6mm 4mm; }

        :root { --content: 50mm; }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: 'Courier New', Courier, monospace;
            /* Ukuran huruf dihitung agar muat tepat 32 karakter per baris di 50mm
               (huruf monospace lebarnya 0.6em => 50mm / (32 x 0.6) ), sama seperti
               printer thermal 58mm pada foto. */
            font-size: calc(var(--content) / 19.2);
            line-height: 1.4;
            font-weight: 400; /* ubah ke 700 jika hasil cetak terlalu tipis */
            color: #000;
            width: 100%;
            max-width: var(--content);
        }

        p { margin: 0; }

        .center { text-align: center; }
        .wrap { overflow-wrap: anywhere; }

        /* Garis putus-putus setinggi satu baris teks */
        .break {
            border-top: 1px dashed #000;
            margin: 0.6em 0;
        }

        /* Baris label (kiri) - nilai (kanan) */
        .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1ch;
        }
        .row > :first-child { flex: 0 0 auto; white-space: nowrap; }
        .row > :last-child { text-align: right; overflow-wrap: anywhere; }

        /* Item: nama di baris sendiri, lalu "qty | harga satuan | subtotal" */
        .item-line {
            display: grid;
            grid-template-columns: 6ch 13ch 1fr;
        }
        .item-line > :nth-child(n+2) { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    @php
        $settings = app(\App\Services\SettingsService::class);
        $business = [
            'name' => $settings->get('business.name', config('app.name')),
            'address' => $settings->get('business.address', ''),
            'phone' => $settings->get('business.phone', ''),
        ];
        $footer = $settings->get('receipt.footer', 'Terima kasih atas kunjungan Anda');
        $totalPaid = $order->payments()->where('status', \App\Models\Payment::STATUS_PAID)->sum('amount');
        $change = max(0, $totalPaid - $order->grand_total);
        $typeLabels = ['dine_in' => 'Dine In', 'take_away' => 'Take Away', 'room_service' => 'Room Service'];
        $money = fn ($n) => number_format((float) $n, 0, ',', '.');

        // Kode pendek (seperti "Kode 1133" pada foto) diambil dari 4 digit terakhir nomor pesanan.
        $shortCode = substr(preg_replace('/\D/', '', (string) $order->order_number), -4);
        $orderedAt = $order->ordered_at?->locale('id')->translatedFormat('H:i, j M');
        $statusLabel = $order->payment_status === \App\Models\Order::PAYMENT_PAID
            ? 'LUNAS'
            : strtoupper($order->payment_status);
    @endphp

    {{-- Header --}}
    <div class="center wrap">
        <p>{{ $business['name'] }}</p>
        @if ($business['address']) <p>{{ $business['address'] }}</p> @endif
        @if ($business['phone']) <p>Telp: {{ $business['phone'] }}</p> @endif
    </div>

    <div class="break"></div>

    {{-- Info pesanan --}}
    <div class="row"><span>No. pesanan</span><span>{{ $order->order_number }}</span></div>
    @if ($shortCode !== '')
        <div class="row"><span>Kode</span><span>{{ $shortCode }}</span></div>
    @endif
    @if ($orderedAt)
        <div class="row"><span>Waktu</span><span>{{ $orderedAt }}</span></div>
    @endif
    @if ($order->customer_name)
        <div class="row"><span>Pelanggan</span><span>{{ $order->customer_name }}</span></div>
    @endif
    @if ($order->area)
        <div class="row"><span>Lokasi</span><span>{{ $order->area->name }} · {{ $order->locationLabel() }}</span></div>
    @endif

    <div class="break"></div>

    {{-- Daftar item --}}
    @foreach ($order->items as $item)
        <div class="wrap">{{ $item->product_name }}</div>
        <div class="item-line">
            <span>{{ $item->quantity }}x</span>
            <span>{{ $money($item->subtotal / max(1, (int) $item->quantity)) }}</span>
            <span>{{ $money($item->subtotal) }}</span>
        </div>
        @if ($item->notes)
            <div class="wrap">Catatan: {{ $item->notes }}</div>
        @endif
        <div class="break"></div>
    @endforeach

    {{-- Ringkasan --}}
    @if ($order->discount_amount > 0 || $order->tax_amount > 0 || $order->service_charge_amount > 0)
        <div class="row"><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
    @endif
    @if ($order->discount_amount > 0)
        <div class="row"><span>Diskon</span><span>-{{ $money($order->discount_amount) }}</span></div>
    @endif
    @if ($order->tax_amount > 0)
        <div class="row"><span>Pajak</span><span>{{ $money($order->tax_amount) }}</span></div>
    @endif
    @if ($order->service_charge_amount > 0)
        <div class="row"><span>SC</span><span>{{ $money($order->service_charge_amount) }}</span></div>
    @endif
    <div class="row"><span>Total</span><span>{{ $money($order->grand_total) }}</span></div>
    <div class="row"><span>Dibayar</span><span>{{ $money($totalPaid) }}</span></div>
    @if ($change > 0)
        <div class="row"><span>Kembalian</span><span>{{ $money($change) }}</span></div>
    @endif

    <div class="break"></div>

    {{-- Pembayaran & tipe pesanan --}}
    @foreach ($order->payments as $payment)
        <div class="row"><span>Pembayaran</span><span>{{ $payment->paymentMethod?->name }}</span></div>
        @if ($payment->reference)
            <div class="row"><span>Ref</span><span>{{ $payment->reference }}</span></div>
        @endif
    @endforeach
    <div class="row"><span>Tipe Pesanan</span><span>{{ $typeLabels[$order->order_type] ?? $order->order_type }}</span></div>
    <div class="row"><span>Status</span><span>{{ $statusLabel }}</span></div>
    @if ($order->creator)
        <div class="row"><span>Kasir</span><span>{{ $order->creator->name }}</span></div>
    @endif

    <div class="break"></div>
    <div class="center wrap">{{ $footer }}</div>
</body>
</html>