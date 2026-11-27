<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrisPaymentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStaffRolesAndSettings();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['stock_type' => 'unlimited', 'stock' => 99]);
    }

    private function qrisMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'QRIS',
            'code' => 'qris',
            'type' => 'online',
            'is_active' => true,
            'sort_order' => 2,
            'config' => ['provider' => 'qris'],
        ]);
    }

    private function debitCardMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'Kartu Debit',
            'code' => 'debit_card',
            'type' => 'online',
            'is_active' => true,
            'sort_order' => 3,
            'config' => ['provider' => 'mock'],
        ]);
    }

    private function addToCart(int $productId): void
    {
        $this->post(route('cart.add'), ['product_id' => $productId, 'quantity' => 1])->assertRedirect();
    }

    private function checkoutWith(int $methodId): Order
    {
        $this->post(route('checkout.store'), [
            'order_type' => Order::TYPE_TAKE_AWAY,
            'payment_method_id' => $methodId,
        ]);

        return Order::firstOrFail();
    }

    private function orderWithoutPayment(): Order
    {
        $this->post(route('checkout.store'), [
            'order_type' => Order::TYPE_TAKE_AWAY,
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    public function test_qris_checkout_redirects_to_qris_payment_page(): void
    {
        $method = $this->qrisMethod();
        $this->product(['sale_price' => 15000]);
        $product = Product::firstOrFail();
        $this->addToCart($product->id);

        $this->post(route('checkout.store'), [
            'order_type' => Order::TYPE_TAKE_AWAY,
            'payment_method_id' => $method->id,
        ])->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->assertSame('qris', $payment->provider);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame((float) $payment->order->grand_total, (float) $payment->amount);
        $this->assertNull($payment->expires_at);

        Storage::fake('public');
        Storage::disk('public')->put('qriss.jpeg', 'QRIS image');

        $this->get(route('payment.qris.pay', $payment))
            ->assertOk()
            ->assertSee('Nominal Dibayar')
            ->assertSee('Rp '.number_format((float) $payment->amount, 0, ',', '.'))
            ->assertSee('storage/qriss.jpeg');
    }

    public function test_cashier_qris_button_records_a_paid_payment_without_gateway_redirect(): void
    {
        $method = $this->qrisMethod();
        $order = Order::factory()->takeAway()->create(['grand_total' => 50000]);

        $this->actAsFresh($this->cashierUser())
            ->post(route('cashier.orders.pay-online', $order), [
                'payment_method_id' => $method->id,
            ])
            ->assertRedirect(route('cashier.receipt.show', $order))
            ->assertSessionHas('success', 'Pembayaran QRIS berhasil dicatat.');

        $payment = $order->payments()->firstOrFail();

        $this->assertSame('qris', $payment->provider);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(50000.0, (float) $payment->amount);
        $this->assertSame(50000.0, (float) $payment->amount_received);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('qris', $payment->paymentMethod->code);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_cashier_order_payment_shows_cash_and_online_methods_in_one_selector(): void
    {
        $cashMethod = PaymentMethod::factory()->create([
            'name' => 'Tunai Kasir',
            'code' => 'cash',
            'type' => 'cash',
            'sort_order' => 1,
        ]);
        $onlineMethod = $this->qrisMethod();
        $order = Order::factory()->takeAway()->create(['grand_total' => 50000]);

        $response = $this->actAsFresh($this->cashierUser())
            ->get(route('cashier.orders.show', $order));

        $response
            ->assertSee('id="cashier-payment-method"', false)
            ->assertSee('Metode Pembayaran')
            ->assertSee('value="'.$cashMethod->id.'" data-type="cash" data-code="cash"', false)
            ->assertSee('value="'.$onlineMethod->id.'" data-type="online" data-code="qris"', false)
            ->assertSee('Bayar QRIS')
            ->assertDontSee('Nominal QRIS')
            ->assertDontSee('Metode Tunai');
    }

    public function test_cashier_qris_nominal_ignored_for_non_qris_method(): void
    {
        $method = $this->debitCardMethod();
        $this->product(['sale_price' => 50000]);
        $this->addToCart(Product::firstOrFail()->id);
        $order = $this->orderWithoutPayment();

        $this->actAsFresh($this->cashierUser())
            ->post(route('cashier.orders.pay-online', $order), [
                'payment_method_id' => $method->id,
                'amount' => 1000,
            ])
            ->assertRedirect();

        $payment = $order->payments()->firstOrFail();

        $this->assertSame('mock', $payment->provider);
        $this->assertSame((float) $order->grand_total, (float) $payment->amount);
    }

    public function test_cashier_can_confirm_qris_payment_settles_order(): void
    {
        $method = $this->qrisMethod();
        $this->product(['sale_price' => 20000]);
        $this->addToCart(Product::firstOrFail()->id);

        $order = $this->checkoutWith($method->id);

        $this->actAsFresh($this->cashierUser())
            ->post(route('cashier.orders.qris-confirm', $order))
            ->assertRedirect(route('cashier.orders.show', $order))
            ->assertSessionHas('success');

        $payment = $order->payments()->firstOrFail();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame((float) $payment->amount, (float) $payment->amount_received);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_qris_confirm_without_pending_payment_errors(): void
    {
        $this->product(['sale_price' => 20000]);
        $this->addToCart(Product::firstOrFail()->id);

        $this->post(route('checkout.store'), [
            'order_type' => Order::TYPE_TAKE_AWAY,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->actAsFresh($this->cashierUser())
            ->post(route('cashier.orders.qris-confirm', $order))
            ->assertSessionHas('error');

        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }
}
