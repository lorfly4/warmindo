<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashierOrderEditService
{
    public function __construct(
        private PricingService $pricing,
        private ProductStockService $productStock,
    ) {}

    /**
     * @param  array<int, int>  $itemQuantities
     */
    public function updateUnpaidOrder(
        Order $order,
        array $itemQuantities,
        ?int $productId,
        int $quantity,
        ?int $cashierId,
    ): Order {
        return DB::transaction(function () use ($order, $itemQuantities, $productId, $quantity, $cashierId): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertEditable($order);

            $items = $order->items()->lockForUpdate()->get();
            $itemIds = $items->modelKeys();
            $unexpectedItemIds = array_diff(array_map('intval', array_keys($itemQuantities)), $itemIds);

            if ($unexpectedItemIds !== []) {
                throw new RuntimeException('Item yang diedit bukan bagian dari order ini.');
            }

            $productIds = $items->pluck('product_id')->filter()->unique();

            if ($productId !== null) {
                $productIds->push($productId);
            }

            $products = Product::withTrashed()
                ->whereIn('id', $productIds->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                if (! array_key_exists($item->id, $itemQuantities)) {
                    continue;
                }

                $updatedQuantity = (int) $itemQuantities[$item->id];
                $product = $products->get($item->product_id);
                $quantityDifference = $updatedQuantity - $item->quantity;

                if ($product?->isLimitedStock() && $quantityDifference > 0) {
                    $this->productStock->decrement($product, $quantityDifference, OrderItem::class, $item->id, (string) $cashierId);
                } elseif ($product?->isLimitedStock() && $quantityDifference < 0) {
                    $this->productStock->increment($product, abs($quantityDifference), Order::class, $order->id, (string) $cashierId);
                }

                if ($updatedQuantity === 0) {
                    $item->delete();

                    continue;
                }

                $item->update([
                    'quantity' => $updatedQuantity,
                    'subtotal' => round((float) $item->price * $updatedQuantity, 2),
                ]);
            }

            if ($productId !== null) {
                $product = $products->get($productId);

                if (! $product || $product->trashed() || ! $product->is_active || ! $product->is_available) {
                    throw new RuntimeException('Produk yang dipilih sedang tidak tersedia.');
                }

                $addedItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'price' => $product->sale_price,
                    'quantity' => $quantity,
                    'subtotal' => round((float) $product->sale_price * $quantity, 2),
                ]);

                if ($product->isLimitedStock()) {
                    $this->productStock->decrement($product, $quantity, OrderItem::class, $addedItem->id, (string) $cashierId);
                }

            }

            $orderItems = $order->items()->get();

            if ($orderItems->isEmpty()) {
                throw new RuntimeException('Order harus memiliki minimal satu produk.');
            }

            $subtotal = round((float) $orderItems->sum('subtotal'), 2);
            $pricingItems = $orderItems->map(fn (OrderItem $item): array => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'price' => (float) $item->price,
                'quantity' => $item->quantity,
            ]);
            $hasKitchenItems = $orderItems->contains(function (OrderItem $item) use ($products, $order): bool {
                return $products->get($item->product_id)?->is_kitchen
                    ?? ($item->product_id === null && $order->has_kitchen_items);
            });
            $totals = $this->pricing->calculate(
                $pricingItems,
                $subtotal,
                minimumDiscount: (float) $order->discount_amount,
            );

            $order->update([
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'service_charge_amount' => $totals['service_charge_amount'],
                'grand_total' => $totals['grand_total'],
                'has_kitchen_items' => $hasKitchenItems,
            ]);

            return $order->fresh(['items']);
        });
    }

    private function assertEditable(Order $order): void
    {
        if ($order->order_status !== Order::STATUS_NEW || $order->payment_status !== Order::PAYMENT_PENDING) {
            throw new RuntimeException('Order hanya dapat diedit sebelum diterima dan sebelum pembayaran.');
        }

        $hasActivePayment = $order->payments()
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PAID])
            ->exists();

        if ($hasActivePayment) {
            throw new RuntimeException('Order tidak dapat diedit setelah pembayaran dimulai.');
        }
    }
}
