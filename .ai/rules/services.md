---
paths:
  - app/Services/InventoryService.php
---

# Services

## InventoryService keeps products.stock canonical
Multi-warehouse inventory: products.stock stays the canonical total used by order checkout (reserve/reverse). inventory_items tracks per-warehouse stock; stockIn/stockOut adjust both products.stock and the warehouse item; transfer only moves between warehouse items leaving products.stock unchanged. Order flows decrement/increment sync to the default (first active) warehouse. lowStockProducts alert on admin.inventory.index is products.stock-based (tests depend on this contract); per-warehouse below-minimum items shown separately via warehouseLowStock.

## Warehouse stock belongs to ingredients
Warehouse inventory is for raw ingredients, not catalog products. Use Ingredient as the identity for inventory_items and manual stock movements. Product stock remains a separate checkout concern; order reserve/reverse may record product movements but must never alter warehouse ingredient balances.

## Separate product checkout stock
Product catalog stock is standalone from warehouse inventory. Checkout reserve/reversal belongs in ProductStockService and product_stock_movements; InventoryService, inventory_items, and inventory_movements manage raw ingredients only. This replaces the earlier rule that synchronized products.stock with warehouse items.
