<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardDailyReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStaffRolesAndSettings();
    }

    public function test_dashboard_compares_today_with_yesterday(): void
    {
        $this->travelTo('2026-10-14 10:00:00');

        $todayStart = now()->startOfDay();
        $paymentMethod = PaymentMethod::factory()->create(['type' => 'cash']);
        $qrisPaymentMethod = PaymentMethod::factory()->create(['type' => 'online', 'code' => 'qris']);

        foreach ([
            ['amount' => 200000, 'paidAt' => $todayStart->copy()->addHours(2)],
            ['amount' => 75000, 'paidAt' => $todayStart->copy()->subSecond()],
        ] as $paymentData) {
            $order = Order::factory()->takeAway()->completed()->create([
                'subtotal' => $paymentData['amount'],
                'grand_total' => $paymentData['amount'],
                'ordered_at' => $paymentData['paidAt'],
                'completed_at' => $paymentData['paidAt'],
            ]);

            $order->payments()->create([
                'payment_method_id' => $paymentMethod->id,
                'type' => 'cash',
                'status' => Payment::STATUS_PAID,
                'amount' => $paymentData['amount'],
                'provider' => 'cash',
                'paid_at' => $paymentData['paidAt'],
            ]);
        }

        $qrisOrder = Order::factory()->takeAway()->completed()->create([
            'subtotal' => 50000,
            'grand_total' => 50000,
            'ordered_at' => $todayStart->copy()->addHours(3),
            'completed_at' => $todayStart->copy()->addHours(3),
        ]);

        $qrisOrder->payments()->create([
            'payment_method_id' => $qrisPaymentMethod->id,
            'type' => 'online',
            'status' => Payment::STATUS_PAID,
            'amount' => 50000,
            'provider' => 'qris',
            'paid_at' => $todayStart->copy()->addHours(3),
        ]);

        $this->actAsFresh($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('grossSales', 250000.0)
            ->assertViewHas('grossSalesPrev', 75000.0)
            ->assertViewHas('qrisRevenue', 50000.0)
            ->assertViewHas('cashRevenue', 200000.0)
            ->assertViewHas('qrisRevenueTotal', 50000.0)
            ->assertViewHas('cashRevenueTotal', 275000.0)
            ->assertViewHas('orderCount', 2)
            ->assertViewHas('orderCountPrev', 1)
            ->assertViewHas('paymentMix', fn ($paymentMix) => (float) $paymentMix->sum('total') === 250000.0);
    }
}
