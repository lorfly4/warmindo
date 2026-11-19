<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\PaymentGateway\GatewayManager;
use App\Services\PaymentService;
use App\Services\ProductStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStaffRolesAndSettings();
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'Tunai',
            'code' => 'cash',
            'type' => 'cash',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function onlineMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'QRIS',
            'code' => 'qris',
            'type' => 'online',
            'is_active' => true,
            'sort_order' => 2,
            'config' => ['provider' => 'mock'],
        ]);
    }

    private function orderWithProduct(array $productAttributes = [], int $quantity = 1): array
    {
        $product = Product::factory()->create($productAttributes + [
            'is_kitchen' => true,
            'stock_type' => 'unlimited',
            'stock' => 999,
        ]);

        $order = Order::create([
            'order_number' => uniqid('ORD-', true),
            'order_type' => Order::TYPE_TAKE_AWAY,
            'subtotal' => $quantity * (float) $product->sale_price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'grand_total' => $quantity * (float) $product->sale_price,
            'order_status' => Order::STATUS_NEW,
            'payment_status' => Order::PAYMENT_PENDING,
            'has_kitchen_items' => true,
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => $product->sale_price,
            'quantity' => $quantity,
            'subtotal' => $quantity * (float) $product->sale_price,
        ]);

        return [$order, $product];
    }

    public function test_kitchen_board_and_order_transitions_are_unavailable(): void
    {
        $kitchen = $this->kitchenUser();
        [$order] = $this->orderWithProduct();

        $this->actingAs($kitchen)
            ->get('/kitchen')
            ->assertNotFound();

        $this->post('/kitchen/orders/'.$order->id.'/status', ['action' => 'start_cooking'])
            ->assertNotFound();

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->order_status);
    }

    public function test_kitchen_cannot_mark_ready_directly_from_new(): void
    {
        $kitchen = $this->kitchenUser();
        [$order] = $this->orderWithProduct();

        $this->actingAs($kitchen)
            ->post('/kitchen/orders/'.$order->id.'/status', ['action' => 'mark_ready'])
            ->assertNotFound();

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_NEW, $fresh->order_status);
    }

    public function test_cashier_can_complete_ready_order(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct();

        $order->update([
            'order_status' => Order::STATUS_READY,
            'ready_at' => now(),
            'payment_status' => Order::PAYMENT_PAID,
        ]);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.complete', $order))
            ->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->order_status);
    }

    public function test_cashier_can_complete_paid_order_without_kitchen_steps(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct();
        $order->update(['payment_status' => Order::PAYMENT_PAID]);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.complete', $order))
            ->assertRedirect();

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->order_status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => Order::STATUS_NEW,
            'to_status' => Order::STATUS_COMPLETED,
            'note' => 'Diselesaikan langsung tanpa melalui dapur.',
        ]);
    }

    public function test_cashier_cannot_complete_unpaid_order(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct();

        $this->actingAs($cashier)
            ->post(route('cashier.orders.complete', $order))
            ->assertRedirect()
            ->assertSessionHas('error', 'Order belum lunas — selesaikan pembayaran terlebih dahulu.');

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->order_status);
    }

    public function test_cashier_dashboard_links_to_transactions_and_order_detail_can_complete(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct();
        $order->update(['payment_status' => Order::PAYMENT_PAID]);

        $this->actingAs($cashier)
            ->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertSee(route('cashier.orders'))
            ->assertSee(route('cashier.orders.create'))
            ->assertDontSee('Antrian Order');

        $this->get(route('cashier.orders.show', $order))
            ->assertOk()
            ->assertSee('Selesaikan Langsung');
    }

    public function test_ac_09_cancellation_requires_reason_and_records_history(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct();

        $this->actingAs($cashier)
            ->post(route('cashier.orders.cancel', $order), [])
            ->assertStatus(302);

        $this->assertSame(Order::STATUS_NEW, $order->fresh()->order_status);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.cancel', $order), [
                'reason' => 'Pelanggan membatalkan pesanan',
                'reason_code' => 'customer_request',
            ])->assertRedirect();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertDatabaseHas('order_cancellations', [
            'order_id' => $order->id,
            'reason_code' => 'customer_request',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => Order::STATUS_CANCELLED,
        ]);
    }

    public function test_br_010_cancellation_reverses_limited_stock(): void
    {
        $cashier = $this->cashierUser();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_kitchen' => true,
            'stock_type' => 'limited',
            'stock' => 5,
            'sale_price' => 15000,
        ]);

        $order = Order::create([
            'order_number' => uniqid('ORD-', true),
            'order_type' => Order::TYPE_TAKE_AWAY,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'grand_total' => 15000,
            'order_status' => Order::STATUS_NEW,
            'payment_status' => Order::PAYMENT_PAID,
            'has_kitchen_items' => true,
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 15000,
            'quantity' => 1,
            'subtotal' => 15000,
        ]);

        app(ProductStockService::class)->decrement($product, 1, OrderItem::class, OrderItem::first()->id);

        $this->assertSame(4, $product->fresh()->stock);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.cancel', $order), [
                'reason' => 'Stok dikembalikan',
                'reason_code' => 'stock_return',
            ])->assertRedirect();

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'type' => 'REVERSAL',
        ]);
    }

    public function test_fr_cas_004_cash_payment_confirms_atomically_with_change(): void
    {
        $cashier = $this->cashierUser();
        $method = $this->cashMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 50000]);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.pay-cash', $order), [
                'payment_method_id' => $method->id,
                'amount_received' => 100000,
            ])->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(100000.0, (float) $payment->amount_received);
        $this->assertSame(50000.0, (float) $payment->change_due);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_cashier_can_edit_and_add_items_before_payment(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct(['sale_price' => 10000]);
        $existingItem = $order->items()->firstOrFail();
        $addedProduct = Product::factory()->create([
            'name' => 'Teh Manis',
            'sale_price' => 12000,
            'is_kitchen' => false,
            'stock_type' => 'limited',
            'stock' => 10,
        ]);

        $this->actAsFresh($cashier)
            ->get(route('cashier.orders.show', $order))
            ->assertOk()
            ->assertSee('Simpan Perubahan Order');

        $this->actAsFresh($cashier)
            ->put(route('cashier.orders.items.update', $order), [
                'items' => [$existingItem->id => 2],
                'product_id' => $addedProduct->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('cashier.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_items', [
            'id' => $existingItem->id,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $addedProduct->id,
            'stock' => 8,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $addedProduct->id,
            'quantity' => 2,
            'subtotal' => 24000,
        ]);
        $this->assertSame('44000.00', $order->fresh()->subtotal);
        $this->assertSame('46200.00', $order->fresh()->grand_total);
    }

    public function test_cashier_cannot_edit_order_after_online_payment_starts(): void
    {
        $cashier = $this->cashierUser();
        [$order] = $this->orderWithProduct(['sale_price' => 10000]);
        $item = $order->items()->firstOrFail();
        $method = $this->onlineMethod();
        Payment::create([
            'order_id' => $order->id,
            'payment_method_id' => $method->id,
            'type' => 'online',
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->grand_total,
            'provider' => 'mock',
        ]);
        $addedProduct = Product::factory()->create(['stock_type' => 'unlimited', 'stock' => 99]);

        $this->actAsFresh($cashier)
            ->put(route('cashier.orders.items.update', $order), [
                'items' => [$item->id => 2],
                'product_id' => $addedProduct->id,
                'quantity' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Order tidak dapat diedit setelah pembayaran dimulai.');

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'quantity' => 1,
            'subtotal' => 10000,
        ]);
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'product_id' => $addedProduct->id,
        ]);
        $this->assertSame('10000.00', $order->fresh()->subtotal);
    }

    public function test_fr_cas_004_cash_payment_rejects_insufficient_amount(): void
    {
        $cashier = $this->cashierUser();
        $method = $this->cashMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 50000]);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.pay-cash', $order), [
                'payment_method_id' => $method->id,
                'amount_received' => 40000,
            ])->assertRedirect();

        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
        $this->assertSame(0, Payment::count());
    }

    public function test_fr_cas_005_cashier_cannot_manually_mark_online_paid_without_override(): void
    {
        $cashier = $this->cashierUser();
        $method = $this->onlineMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 25000]);

        $this->actingAs($cashier)
            ->post(route('cashier.orders.pay-cash', $order), [
                'payment_method_id' => $method->id,
                'amount_received' => 25000,
            ])->assertRedirect();

        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }

    public function test_ac_05_bad_webhook_signature_does_not_mark_paid(): void
    {
        $method = $this->onlineMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 10000]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method_id' => $method->id,
            'type' => 'online',
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->grand_total,
            'provider' => 'mock',
            'external_id' => 'EXT-12345',
        ]);

        $this->postJson(route('webhook.payment.mock'), [
            'event' => 'payment.paid',
            'external_id' => 'EXT-12345',
            'signature' => 'invalid-signature',
        ])->assertStatus(400);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }

    public function test_valid_webhook_marks_online_payment_paid(): void
    {
        $method = $this->onlineMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 10000]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method_id' => $method->id,
            'type' => 'online',
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->grand_total,
            'provider' => 'mock',
            'external_id' => 'EXT-VALID-1',
        ]);

        $gateway = app(GatewayManager::class)->driver('mock');

        $payload = [
            'event' => 'payment.paid',
            'external_id' => 'EXT-VALID-1',
            'amount' => (string) $payment->amount,
        ];
        $payload['signature'] = $gateway->sign($payload);

        $this->postJson(route('webhook.payment.mock'), $payload)->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_fr_pay_006_failed_payment_keeps_order_and_allows_retry(): void
    {
        $method = $this->onlineMethod();
        [$order] = $this->orderWithProduct(['sale_price' => 10000]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method_id' => $method->id,
            'type' => 'online',
            'status' => Payment::STATUS_PENDING,
            'amount' => $order->grand_total,
            'provider' => 'mock',
            'external_id' => 'EXT-RETRY-1',
        ]);

        app(PaymentService::class)->failOnline($payment, 'DECLINED');

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(Order::STATUS_NEW, $order->fresh()->order_status);
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }
}
