<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductStockService
{
    /**
     * @param  Collection<int, Product>  $products  indexed by id
     */
    public function reserve(Order $order, Collection $products): void
    {
        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);

            if (! $product || ! $product->isLimitedStock()) {
                continue;
            }

            $this->decrement($product, $item->quantity, OrderItem::class, $item->id);
        }
    }

    public function reverse(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::withTrashed()->find($item->product_id);

            if (! $product || ! $product->isLimitedStock()) {
                continue;
            }

            $this->increment($product, $item->quantity, Order::class, $order->id);
        }
    }

    public function decrement(Product $product, int $quantity, string $referenceType = 'NONE', ?int $referenceId = null, ?string $actorId = null): void
    {
        $product->refresh();
        $before = (int) $product->stock;

        if ($before < $quantity) {
            throw new RuntimeException('Stok produk tidak mencukupi untuk '.$product->name);
        }

        $affected = DB::table('products')
            ->where('id', $product->id)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($affected === 0) {
            throw new RuntimeException('Stok produk tidak mencukupi untuk '.$product->name);
        }

        $this->movement($product, ProductStockMovement::TYPE_OUT, $quantity, $before, $before - $quantity, $referenceType, $referenceId, $actorId);
    }

    public function increment(Product $product, int $quantity, string $referenceType = 'NONE', ?int $referenceId = null, ?string $actorId = null): void
    {
        $product->refresh();
        $before = (int) $product->stock;
        $product->increment('stock', $quantity);

        $this->movement($product, ProductStockMovement::TYPE_REVERSAL, $quantity, $before, $before + $quantity, $referenceType, $referenceId, $actorId);
    }

    private function movement(Product $product, string $type, int $quantity, int $before, int $after, string $referenceType, ?int $referenceId, ?string $actorId): void
    {
        ProductStockMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit' => 'pcs',
            'before' => $before,
            'after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }
}
