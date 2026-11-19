<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    private const ALLOWED_UNITS = ['pcs', 'kg', 'g', 'ml', 'L'];

    public function adjust(Ingredient $ingredient, int $newStock, ?string $note = null, ?string $actorId = null, ?int $warehouseId = null, string $unit = 'pcs'): void
    {
        $this->assertAllowedUnit($unit);

        if ($newStock < 0) {
            throw new RuntimeException('Stok tidak boleh negatif');
        }

        $warehouse = Warehouse::find($warehouseId) ?? $this->defaultWarehouse();
        $item = $this->ensureInventoryItem($warehouse, $ingredient);

        if ($item->quantity > 0 && $item->unit !== $unit) {
            throw new RuntimeException('Satuan gudang tidak cocok dengan satuan yang dipilih');
        }

        $before = (int) $item->quantity;
        $delta = $newStock - $before;

        DB::transaction(function () use ($item, $delta, $unit) {
            if ($item->quantity + $delta < 0) {
                throw new RuntimeException('Stok gudang tidak mencukupi');
            }
            $item->update(['quantity' => $item->quantity + $delta, 'unit' => $unit]);
        });

        $this->movement($ingredient, InventoryMovement::TYPE_ADJUSTMENT, abs($delta), $before, $item->refresh()->quantity, 'ADMIN', null, $actorId, $note, $warehouse, null, null, $item->unit);
    }

    /**
     * Record incoming stock from a supplier into a specific warehouse.
     */
    public function stockIn(
        Ingredient $ingredient,
        Warehouse $warehouse,
        int $quantity,
        ?Supplier $supplier = null,
        ?string $note = null,
        ?float $unitCost = null,
        ?string $actorId = null,
        string $unit = 'pcs',
    ): void {
        $this->assertAllowedUnit($unit);

        $item = $this->ensureInventoryItem($warehouse, $ingredient);

        if ($item->quantity > 0 && $item->unit !== $unit) {
            throw new RuntimeException('Satuan gudang tidak cocok dengan satuan yang dipilih');
        }

        $before = (int) $item->quantity;
        $itemAfter = $before + $quantity;

        DB::transaction(function () use ($item, $itemAfter, $unit) {
            $item->update(['quantity' => $itemAfter, 'unit' => $unit]);
        });

        $this->movement(
            $ingredient,
            InventoryMovement::TYPE_IN,
            $quantity,
            $before,
            $itemAfter,
            Supplier::class,
            $supplier?->id,
            $actorId,
            $note,
            $warehouse,
            $supplier,
            $unitCost,
            null,
            $unit,
        );
    }

    /**
     * Remove stock from a specific warehouse (e.g. damaged, expired, waste).
     */
    public function stockOut(
        Ingredient $ingredient,
        Warehouse $warehouse,
        int $quantity,
        ?string $note = null,
        ?string $actorId = null,
        string $unit = 'pcs',
    ): void {
        $this->assertAllowedUnit($unit);

        $item = $this->getInventoryItem($warehouse, $ingredient);

        if (! $item || $item->quantity < $quantity) {
            throw new RuntimeException('Stok gudang tidak mencukupi untuk '.$ingredient->name);
        }

        if ($item->unit !== $unit) {
            throw new RuntimeException('Satuan gudang tidak cocok dengan satuan yang dipilih');
        }

        $before = (int) $item->quantity;
        $after = $before - $quantity;

        DB::transaction(function () use ($item, $quantity, $ingredient) {
            $affected = InventoryItem::query()
                ->whereKey($item->id)
                ->where('quantity', '>=', $quantity)
                ->decrement('quantity', $quantity);

            if ($affected === 0) {
                throw new RuntimeException('Stok gudang tidak mencukupi untuk '.$ingredient->name);
            }
        });

        $this->movement(
            $ingredient,
            InventoryMovement::TYPE_OUT,
            $quantity,
            $before,
            $after,
            'MANUAL',
            null,
            $actorId,
            $note,
            $warehouse,
            null,
            null,
            null,
            $unit,
        );
    }

    /**
     * Move ingredient stock between warehouses.
     */
    public function transfer(
        Ingredient $ingredient,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        int $quantity,
        ?string $note = null,
        ?string $actorId = null,
        string $unit = 'pcs',
    ): void {
        $this->assertAllowedUnit($unit);

        if ($fromWarehouse->id === $toWarehouse->id) {
            throw new RuntimeException('Gudang asal dan tujuan tidak boleh sama');
        }

        $from = $this->getInventoryItem($fromWarehouse, $ingredient);

        if (! $from || $from->quantity < $quantity) {
            throw new RuntimeException('Stok gudang asal tidak mencukupi untuk '.$ingredient->name);
        }

        if ($from->unit !== $unit) {
            throw new RuntimeException('Satuan gudang asal tidak cocok dengan satuan yang dipilih');
        }

        $to = $this->ensureInventoryItem($toWarehouse, $ingredient);

        if ($to->quantity > 0 && $to->unit !== $unit) {
            throw new RuntimeException('Satuan gudang tujuan tidak cocok dengan satuan yang dipilih');
        }

        if ($to->quantity === 0) {
            $to->update(['unit' => $unit]);
        }

        DB::transaction(function () use ($from, $to, $quantity, $ingredient) {
            $affected = InventoryItem::query()
                ->whereKey($from->id)
                ->where('quantity', '>=', $quantity)
                ->decrement('quantity', $quantity);

            if ($affected === 0) {
                throw new RuntimeException('Stok gudang asal tidak mencukupi untuk '.$ingredient->name);
            }

            $to->increment('quantity', $quantity);
        });

        $this->movement(
            $ingredient,
            InventoryMovement::TYPE_TRANSFER,
            $quantity,
            (int) $from->quantity,
            (int) $from->quantity - $quantity,
            'TRANSFER',
            null,
            $actorId,
            $note,
            $fromWarehouse,
            null,
            null,
            $toWarehouse,
            $unit,
        );
    }

    private function ensureInventoryItem(Warehouse $warehouse, Ingredient $ingredient): InventoryItem
    {
        return InventoryItem::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'ingredient_id' => $ingredient->id],
            ['quantity' => 0, 'unit' => 'pcs', 'min_threshold' => 0],
        );
    }

    private function getInventoryItem(Warehouse $warehouse, Ingredient $ingredient): ?InventoryItem
    {
        return InventoryItem::where('warehouse_id', $warehouse->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();
    }

    public function defaultWarehouse(): Warehouse
    {
        return Warehouse::where('is_active', true)->orderBy('id')->first()
            ?? Warehouse::create([
                'name' => 'Gudang Utama',
                'slug' => 'gudang-utama',
                'is_active' => true,
            ]);
    }

    private function movement(
        Ingredient $ingredient,
        string $type,
        int $quantity,
        int $before,
        int $after,
        string $refType = 'NONE',
        ?int $refId = null,
        ?string $actorId = null,
        ?string $note = null,
        ?Warehouse $warehouse = null,
        ?Supplier $supplier = null,
        ?float $unitCost = null,
        ?Warehouse $toWarehouse = null,
        string $unit = 'pcs',
    ): void {
        InventoryMovement::create([
            'ingredient_id' => $ingredient->id,
            'warehouse_id' => $warehouse?->id,
            'to_warehouse_id' => $toWarehouse?->id,
            'supplier_id' => $supplier?->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit' => $unit,
            'before' => $before,
            'after' => $after,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'actor_id' => $actorId,
            'note' => $note,
            'unit_cost' => $unitCost,
            'created_at' => now(),
        ]);
    }

    private function assertAllowedUnit(string $unit): void
    {
        if (! in_array($unit, self::ALLOWED_UNITS, true)) {
            throw new RuntimeException('Satuan tidak didukung: '.$unit);
        }
    }
}
