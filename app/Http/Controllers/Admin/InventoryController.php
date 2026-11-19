<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('inventory.view');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        $warehouseId = $request->warehouse ?? null;

        $items = InventoryItem::query()
            ->with(['ingredient', 'warehouse'])
            ->when($warehouseId, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->search, fn ($q, $s) => $q->whereHas('ingredient', fn ($ingredient) => $ingredient->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")))
            ->orderBy('quantity')
            ->paginate(20)
            ->withQueryString();

        $warehouseLowStock = InventoryItem::query()
            ->with(['ingredient', 'warehouse'])
            ->whereHas('ingredient', fn ($ingredient) => $ingredient->where('is_active', true))
            ->whereColumn('quantity', '<=', 'min_threshold')
            ->orderBy('quantity')
            ->limit(10)
            ->get();

        return view('admin.inventory.index', compact('warehouses', 'warehouseId', 'items', 'warehouseLowStock'));
    }

    public function movements(Request $request): View
    {
        Gate::authorize('inventory.view');

        $movements = InventoryMovement::with(['ingredient', 'actor', 'warehouse', 'toWarehouse', 'supplier'])
            ->whereNotNull('ingredient_id')
            ->when($request->ingredient, fn ($q, $search) => $q->whereHas('ingredient', fn ($ingredient) => $ingredient->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->warehouse, fn ($q, $w) => $q->where(function ($q) use ($w) {
                $q->where('warehouse_id', $w)->orWhere('to_warehouse_id', $w);
            }))
            ->when($request->date, fn ($q, $date) => $q->whereDate('created_at', $date))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::orderBy('name')->get();

        return view('admin.inventory.movements', compact('movements', 'warehouses'));
    }

    public function adjust(Request $request, Ingredient $ingredient): RedirectResponse
    {
        Gate::authorize('inventory.update');

        $data = $request->validate([
            'new_stock' => ['required', 'integer', 'min:0'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'unit' => ['nullable', 'in:pcs,kg,g,ml,L'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->inventory->adjust($ingredient, $data['new_stock'], $data['note'] ?? null, (string) auth()->id(), $data['warehouse_id'] ?? null, $data['unit'] ?? 'pcs');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('adjust', 'inventory', 'ingredient', $ingredient->id, [], ['new_stock' => $data['new_stock'], 'unit' => $data['unit'] ?? 'pcs'], auth()->id());

        return back()->with('success', 'Stok gudang berhasil disesuaikan.');
    }

    public function stockInForm(Request $request): View
    {
        Gate::authorize('inventory.update');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::query()
            ->where('is_active', true)
            ->when($request->ingredient, fn ($q, $s) => $q->where(fn ($ingredient) => $ingredient->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")))
            ->orderBy('name')
            ->get();

        return view('admin.inventory.stock-in', compact('warehouses', 'suppliers', 'ingredients'));
    }

    public function stockIn(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.update');

        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'in:pcs,kg,g,ml,L'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $supplier = isset($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;

        try {
            $this->inventory->stockIn(
                $ingredient,
                $warehouse,
                $data['quantity'],
                $supplier,
                $data['note'] ?? null,
                isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                (string) auth()->id(),
                $data['unit'],
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->log('stock_in', 'inventory', 'ingredient', $ingredient->id, [], ['quantity' => $data['quantity'], 'unit' => $data['unit']], auth()->id());

        return redirect()->route('admin.inventory.movements')->with('success', 'Stok masuk berhasil dicatat.');
    }

    public function stockOutForm(): View
    {
        Gate::authorize('inventory.update');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.inventory.stock-out', compact('warehouses', 'ingredients'));
    }

    public function stockOut(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.update');

        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'in:pcs,kg,g,ml,L'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        try {
            $this->inventory->stockOut($ingredient, $warehouse, $data['quantity'], $data['note'] ?? null, (string) auth()->id(), $data['unit']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->log('stock_out', 'inventory', 'ingredient', $ingredient->id, [], ['quantity' => $data['quantity'], 'unit' => $data['unit']], auth()->id());

        return redirect()->route('admin.inventory.movements')->with('success', 'Stok keluar berhasil dicatat.');
    }

    public function transferForm(): View
    {
        Gate::authorize('inventory.update');

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.inventory.transfer', compact('warehouses', 'ingredients'));
    }

    public function transfer(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.update');

        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'in:pcs,kg,g,ml,L'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $from = Warehouse::findOrFail($data['from_warehouse_id']);
        $to = Warehouse::findOrFail($data['to_warehouse_id']);

        try {
            $this->inventory->transfer($ingredient, $from, $to, $data['quantity'], $data['note'] ?? null, (string) auth()->id(), $data['unit']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->log('transfer', 'inventory', 'ingredient', $ingredient->id, [], ['quantity' => $data['quantity'], 'unit' => $data['unit'], 'from' => $from->name, 'to' => $to->name], auth()->id());

        return redirect()->route('admin.inventory.movements')->with('success', 'Transfer stok berhasil.');
    }
}
