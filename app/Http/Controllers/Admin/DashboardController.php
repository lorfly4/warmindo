<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('report.view');
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $yesterdayStart = $todayStart->copy()->subDay();

        $currentPaidPayments = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $todayStart)
            ->where('paid_at', '<', $tomorrowStart);
        $previousPaidPayments = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $yesterdayStart)
            ->where('paid_at', '<', $todayStart);

        $grossSales = (float) (clone $currentPaidPayments)->sum('amount');
        $grossSalesPrev = (float) (clone $previousPaidPayments)->sum('amount');
        $qrisRevenue = (float) (clone $currentPaidPayments)->where('provider', 'qris')->sum('amount');
        $cashRevenue = (float) (clone $currentPaidPayments)->where('provider', 'cash')->sum('amount');
        $lifetimePaymentTotals = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->selectRaw("SUM(CASE WHEN provider = 'qris' THEN amount ELSE 0 END) as qris_total")
            ->selectRaw("SUM(CASE WHEN provider = 'cash' THEN amount ELSE 0 END) as cash_total")
            ->first();
        $qrisRevenueTotal = (float) ($lifetimePaymentTotals->qris_total ?? 0);
        $cashRevenueTotal = (float) ($lifetimePaymentTotals->cash_total ?? 0);

        $orderCount = (clone $currentPaidPayments)->distinct()->count('order_id');

        $orderCountPrev = (clone $previousPaidPayments)->distinct()->count('order_id');

        $avgOrderValue = $orderCount > 0 ? round($grossSales / $orderCount, 2) : 0;
        $avgOrderValuePrev = $orderCountPrev > 0 ? round($grossSalesPrev / $orderCountPrev, 2) : 0;

        $saleTrend = $this->trend($grossSales, $grossSalesPrev);
        $orderTrend = $this->trend($orderCount, $orderCountPrev);
        $avgTrend = $this->trend($avgOrderValue, $avgOrderValuePrev);

        $statusDistribution = Order::query()
            ->select('order_status', DB::raw('count(*) as total'))
            ->where('ordered_at', '>=', $todayStart)
            ->where('ordered_at', '<', $tomorrowStart)
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        $currentPaidOrderIds = (clone $currentPaidPayments)->select('order_id')->distinct();
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.id', $currentPaidOrderIds)
            ->select('order_items.product_name', DB::raw('sum(order_items.quantity) as qty'))
            ->groupBy('order_items.product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $paymentMix = Payment::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->where('payments.paid_at', '>=', $todayStart)
            ->where('payments.paid_at', '<', $tomorrowStart)
            ->select('payment_methods.name', DB::raw('count(*) as count'), DB::raw('sum(payments.amount) as total'))
            ->groupBy('payment_methods.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $recentOrders = Order::with('table', 'room')
            ->where('ordered_at', '>=', $todayStart)
            ->where('ordered_at', '<', $tomorrowStart)
            ->orderByDesc('ordered_at')
            ->limit(10)
            ->get();

        $lowStock = Product::where('stock_type', 'limited')
            ->where('stock', '<=', 10)
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'grossSales',
            'grossSalesPrev',
            'qrisRevenue',
            'cashRevenue',
            'qrisRevenueTotal',
            'cashRevenueTotal',
            'orderCount',
            'orderCountPrev',
            'avgOrderValue',
            'avgOrderValuePrev',
            'saleTrend',
            'orderTrend',
            'avgTrend',
            'statusDistribution',
            'topProducts',
            'paymentMix',
            'recentOrders',
            'lowStock',
        ));
    }

    private function trend(float|int $current, float|int $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
