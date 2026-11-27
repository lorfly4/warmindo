<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseInventoryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_only_updates_warehouse_stock_not_product_stock(): void
    {
        $product = Product::factory()->create(['stock_type' => 'limited', 'stock' => 5]);
        $ingredient = Ingredient::create(['name' => 'Cabai', 'is_active' => true]);
        $warehouse = Warehouse::factory()->create();

        app(InventoryService::class)->stockIn(
            $ingredient,
            $warehouse,
            10,
        );

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(10, $warehouse->inventoryItems()->where('ingredient_id', $ingredient->id)->first()->quantity);
    }

    public function test_movement_history_displays_supplier_name_for_class_reference(): void
    {
        $this->seedStaffRolesAndSettings();

        $supplier = Supplier::create(['name' => 'Supplier Berkah']);
        $ingredient = Ingredient::create(['name' => 'Cabai', 'is_active' => true]);
        $warehouse = Warehouse::create(['name' => 'Gudang Uji', 'slug' => 'gudang-uji']);
        app(InventoryService::class)->stockIn($ingredient, $warehouse, 10, $supplier);

        $this->actingAs($this->adminUser())
            ->get(route('admin.inventory.movements'))
            ->assertOk()
            ->assertSeeText('Supplier Berkah');
    }

    public function test_stock_out_only_updates_warehouse_stock_not_product_stock(): void
    {
        $product = Product::factory()->create(['stock_type' => 'limited', 'stock' => 5]);
        $ingredient = Ingredient::create(['name' => 'Bawang', 'is_active' => true]);
        $warehouse = Warehouse::factory()->create();

        app(InventoryService::class)->stockIn($ingredient, $warehouse, 10);
        app(InventoryService::class)->stockOut($ingredient, $warehouse, 4);

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(6, $warehouse->inventoryItems()->where('ingredient_id', $ingredient->id)->first()->quantity);
    }

    public function test_adjustment_only_updates_warehouse_stock_not_product_stock(): void
    {
        $product = Product::factory()->create(['stock_type' => 'limited', 'stock' => 5]);
        $ingredient = Ingredient::create(['name' => 'Jahe', 'is_active' => true]);
        $warehouse = Warehouse::factory()->create();

        app(InventoryService::class)->stockIn($ingredient, $warehouse, 10);
        app(InventoryService::class)->adjust($ingredient, 15, null, null, $warehouse->id);

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(15, $warehouse->inventoryItems()->where('ingredient_id', $ingredient->id)->first()->quantity);
    }

    public function test_product_checkout_stock_changes_do_not_change_warehouse_stock(): void
    {
        $product = Product::factory()->create(['stock_type' => 'limited', 'stock' => 10]);
        $warehouse = Warehouse::factory()->create();

        $ingredient = Ingredient::create(['name' => 'Beras', 'is_active' => true]);
        app(InventoryService::class)->stockIn($ingredient, $warehouse, 8);
        app(ProductStockService::class)->decrement($product, 2);

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(8, $warehouse->inventoryItems()->where('ingredient_id', $ingredient->id)->first()->quantity);
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'type' => 'OUT',
        ]);
        $this->assertDatabaseMissing('inventory_movements', [
            'ingredient_id' => $ingredient->id,
            'type' => 'OUT',
        ]);
    }

    public function test_stock_cannot_be_moved_between_units_without_emptying_item(): void
    {
        $ingredient = Ingredient::create(['name' => 'Garam', 'is_active' => true]);
        $warehouse = Warehouse::factory()->create();
        $inventory = app(InventoryService::class);

        $inventory->stockIn($ingredient, $warehouse, 5, unit: 'kg');

        $this->expectException(\RuntimeException::class);
        $inventory->stockIn($ingredient, $warehouse, 2, unit: 'g');
    }
}
