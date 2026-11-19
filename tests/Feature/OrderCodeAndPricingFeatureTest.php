<?php

namespace Tests\Feature;

use App\Services\OrderNumberGenerator;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCodeAndPricingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_code_is_between_ten_and_three_hundred_characters(): void
    {
        $generator = app(OrderNumberGenerator::class);

        $code = $generator->generate();

        $this->assertGreaterThanOrEqual(10, strlen($code));
        $this->assertLessThanOrEqual(300, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9-]+$/', $code);
    }

    public function test_tax_is_disabled_and_total_uses_subtotal_after_discount(): void
    {
        $pricing = app(PricingService::class)->calculate(
            collect([[
                'product_id' => 1,
                'product_name' => 'Produk',
                'price' => 100000.0,
                'quantity' => 1,
            ]]),
            100000.0,
        );

        $this->assertSame(0.0, $pricing['tax_amount']);
        $this->assertSame(0.0, $pricing['service_charge_amount']);
        $this->assertSame(100000.0, $pricing['grand_total']);
    }
}
