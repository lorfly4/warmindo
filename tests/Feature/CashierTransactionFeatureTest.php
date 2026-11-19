<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierTransactionFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStaffRolesAndSettings();
    }

    public function test_customer_and_kitchen_pages_are_unavailable(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/menu')->assertNotFound();
        $this->get('/cart')->assertNotFound();
        $this->post('/checkout')->assertNotFound();
        $this->get('/track/ORD-UNKNOWN')->assertNotFound();
        $this->get('/kitchen')->assertNotFound();
    }

    public function test_cashier_can_create_transaction_with_selected_products_and_quantities(): void
    {
        $cashier = $this->cashierUser();
        $food = Product::factory()->create([
            'name' => 'Nasi Goreng',
            'sale_price' => 25000,
            'stock_type' => 'limited',
            'stock' => 5,
            'is_kitchen' => false,
        ]);
        $drink = Product::factory()->create([
            'name' => 'Es Teh',
            'sale_price' => 5000,
            'stock_type' => 'unlimited',
            'stock' => 0,
            'is_kitchen' => false,
        ]);
        $unselectedProduct = Product::factory()->create([
            'name' => 'Produk Tidak Dipilih',
            'stock_type' => 'unlimited',
        ]);
        $table = DiningTable::factory()->create(['table_number' => 'T001']);

        $this->actingAs($cashier)
            ->get(route('cashier.orders.create'))
            ->assertOk()
            ->assertSee('Nasi Goreng')
            ->assertSee('Es Teh')
            ->assertSee('Take Away')
            ->assertSee('Dine In')
            ->assertSee('Pilih Meja')
            ->assertSee($table->table_number)
            ->assertSee('Rp 25.000')
            ->assertSee('Stok: 5')
            ->assertSee('Stok: Tidak terbatas')
            ->assertSee('Cari nama produk...')
            ->assertSee('Kurangi jumlah Nasi Goreng')
            ->assertSee('Tambah jumlah Nasi Goreng');

        $response = $this->post(route('cashier.orders.store'), [
            'order_type' => Order::TYPE_TAKE_AWAY,
            'product_ids' => [$food->id, $drink->id],
            'quantities' => [
                $food->id => 2,
                $drink->id => 3,
                $unselectedProduct->id => 0,
            ],
        ])->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('cashier.orders.show', $order));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'created_by' => $cashier->id,
            'order_type' => Order::TYPE_TAKE_AWAY,
            'order_status' => Order::STATUS_NEW,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $food->id,
            'product_name' => 'Nasi Goreng',
            'quantity' => 2,
            'subtotal' => 50000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $drink->id,
            'product_name' => 'Es Teh',
            'quantity' => 3,
            'subtotal' => 15000,
        ]);
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'product_id' => $unselectedProduct->id,
        ]);
        $this->assertSame(3, $food->fresh()->stock);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'order',
            'target_id' => $order->id,
            'user_id' => $cashier->id,
        ]);

        $this->get(route('cashier.orders'))
            ->assertOk()
            ->assertSee('Nasi Goreng × 2')
            ->assertSee('Es Teh × 3');
    }

    public function test_cashier_cannot_create_transaction_without_selecting_a_product(): void
    {
        $cashier = $this->cashierUser();
        Product::factory()->create();

        $this->actingAs($cashier)
            ->from(route('cashier.orders.create'))
            ->post(route('cashier.orders.store'), [
                'order_type' => Order::TYPE_TAKE_AWAY,
                'product_ids' => [],
                'quantities' => [],
            ])
            ->assertRedirect(route('cashier.orders.create'))
            ->assertSessionHasErrors('product_ids');

        $this->assertSame(0, Order::count());
    }

    public function test_cashier_cannot_sell_more_than_available_stock(): void
    {
        $cashier = $this->cashierUser();
        $product = Product::factory()->create([
            'name' => 'Stok Terbatas',
            'stock_type' => 'limited',
            'stock' => 1,
        ]);

        $this->actingAs($cashier)
            ->from(route('cashier.orders.create'))
            ->post(route('cashier.orders.store'), [
                'order_type' => Order::TYPE_TAKE_AWAY,
                'product_ids' => [$product->id],
                'quantities' => [$product->id => 2],
            ])
            ->assertRedirect(route('cashier.orders.create'))
            ->assertSessionHas('error', 'Stok Stok Terbatas tidak mencukupi');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_cashier_can_create_dine_in_transaction_for_available_table(): void
    {
        $cashier = $this->cashierUser();
        $area = Area::factory()->create();
        $table = DiningTable::factory()->create([
            'area_id' => $area->id,
            'table_number' => 'T101',
            'status' => 'available',
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'stock_type' => 'unlimited',
            'is_kitchen' => false,
        ]);

        $response = $this->actingAs($cashier)->post(route('cashier.orders.store'), [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'product_ids' => [$product->id],
            'quantities' => [$product->id => 1],
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('cashier.orders.show', $order));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'area_id' => $area->id,
        ]);
    }

    public function test_cashier_must_select_an_available_table_for_dine_in_transaction(): void
    {
        $cashier = $this->cashierUser();
        $product = Product::factory()->create();

        $this->actingAs($cashier)
            ->from(route('cashier.orders.create'))
            ->post(route('cashier.orders.store'), [
                'order_type' => Order::TYPE_DINE_IN,
                'table_id' => '',
                'product_ids' => [$product->id],
                'quantities' => [$product->id => 1],
            ])
            ->assertRedirect(route('cashier.orders.create'))
            ->assertSessionHasErrors([
                'table_id' => 'Pilih meja untuk pesanan dine in.',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_cashier_cannot_use_an_occupied_table_for_dine_in_transaction(): void
    {
        $cashier = $this->cashierUser();
        $table = DiningTable::factory()->create(['status' => 'occupied']);
        $product = Product::factory()->create();

        $this->actingAs($cashier)
            ->from(route('cashier.orders.create'))
            ->post(route('cashier.orders.store'), [
                'order_type' => Order::TYPE_DINE_IN,
                'table_id' => $table->id,
                'product_ids' => [$product->id],
                'quantities' => [$product->id => 1],
            ])
            ->assertRedirect(route('cashier.orders.create'))
            ->assertSessionHasErrors([
                'table_id' => 'Meja yang dipilih tidak tersedia.',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_staff_without_order_create_permission_cannot_create_transaction(): void
    {
        $kitchen = $this->kitchenUser();

        $this->actingAs($kitchen)
            ->get(route('cashier.orders.create'))
            ->assertForbidden();
    }

    public function test_cashier_can_view_inventory_without_stock_update_controls(): void
    {
        $cashier = $this->cashierUser();

        $this->actingAs($cashier)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Inventori')
            ->assertSee('Riwayat Pergerakan')
            ->assertDontSee('Stok Masuk');

        $this->get(route('admin.inventory.stock-in.form'))->assertForbidden();
    }
}
