<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\StoreCashierOrderRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class CashierController extends Controller
{
    public function dashboard(): View
    {
        Gate::authorize('order.view');

        return view('cashier.dashboard');
    }

    public function orders(): View
    {
        Gate::authorize('order.view');

        $orders = Order::with(['items', 'payments.paymentMethod'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('cashier.orders', compact('orders'));
    }

    public function create(): View
    {
        Gate::authorize('order.create');

        $products = Product::query()
            ->visible()
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price', 'stock_type', 'stock']);
        $tables = DiningTable::query()
            ->with('area')
            ->where('is_active', true)
            ->where('status', 'available')
            ->orderBy('table_number')
            ->get(['id', 'area_id', 'table_number', 'name']);

        return view('cashier.create', compact('products', 'tables'));
    }

    public function store(
        StoreCashierOrderRequest $request,
        CheckoutService $checkout,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $table = null;

        if ($validated['order_type'] === Order::TYPE_DINE_IN) {
            $table = DiningTable::query()
                ->where('is_active', true)
                ->where('status', 'available')
                ->find($validated['table_id']);

            if ($table === null) {
                return back()->withInput()->withErrors([
                    'table_id' => 'Meja yang dipilih sudah tidak tersedia. Silakan pilih meja lain.',
                ]);
            }
        }

        $items = collect($validated['product_ids'])
            ->map(fn (string|int $productId): array => [
                'product_id' => (int) $productId,
                'quantity' => (int) $validated['quantities'][$productId],
            ])
            ->values()
            ->all();

        try {
            $result = $checkout->checkout($items, $validated['order_type'], [
                'created_by' => $request->user()->id,
                'table_id' => $table?->id,
                'area_id' => $table?->area_id,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $order = $result['order'];

        $audit->log('create', 'order', 'order', $order->id, [], [
            'order_number' => $order->order_number,
            'grand_total' => $order->grand_total,
        ], $request->user()->id);

        return redirect()->route('cashier.orders.show', $order)
            ->with('success', "Transaksi {$order->order_number} berhasil dibuat.");
    }

    public function show(Order $order): View
    {
        Gate::authorize('order.view');

        $order->load(['items', 'table', 'room', 'area', 'payments.paymentMethod', 'statusHistories.actor', 'cancellation.actor']);
        $methods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
        $products = Product::query()
            ->visible()
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price', 'stock_type', 'stock']);

        return view('cashier.order-show', compact('order', 'methods', 'products'));
    }

    public function history(Request $request): View
    {
        Gate::authorize('order.view');

        $orders = Order::query()
            ->with(['table', 'room', 'area', 'payments.paymentMethod'])
            ->when($request->filled('date_from'), fn ($q, $v) => $q->whereDate('ordered_at', '>=', $v))
            ->when($request->filled('date_to'), fn ($q, $v) => $q->whereDate('ordered_at', '<=', $v))
            ->when($request->filled('status'), fn ($q, $v) => $q->where('order_status', $v))
            ->when($request->filled('order_number'), fn ($q, $v) => $q->where('order_number', 'like', "%{$v}%"))
            ->when($request->filled('payment_method_id'), fn ($q, $v) => $q->whereHas('payments', fn ($p) => $p->where('payment_method_id', $v)))
            ->whereIn('order_status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED])
            ->orderByDesc('ordered_at')
            ->paginate(25)
            ->withQueryString();

        $methods = PaymentMethod::where('is_active', true)->get();

        return view('cashier.history', compact('orders', 'methods'));
    }
}
