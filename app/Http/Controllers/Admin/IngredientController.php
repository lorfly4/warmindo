<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
        Gate::authorize('inventory.update');
    }

    public function index(Request $request): View
    {
        $ingredients = Ingredient::query()
            ->withCount('inventoryItems')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.ingredients.index', compact('ingredients'));
    }

    public function create(): View
    {
        return view('admin.ingredients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:ingredients,sku'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $ingredient = Ingredient::create($data);
        $this->audit->log('create', 'inventory', 'ingredient', $ingredient->id, [], $ingredient->toArray(), auth()->id());

        return redirect()->route('admin.ingredients.index')->with('success', 'Bahan baku berhasil dibuat.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('admin.ingredients.edit', compact('ingredient'));
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('ingredients', 'sku')->ignore($ingredient->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $old = $ingredient->toArray();
        $ingredient->update($data);
        $this->audit->log('update', 'inventory', 'ingredient', $ingredient->id, $old, $ingredient->fresh()->toArray(), auth()->id());

        return redirect()->route('admin.ingredients.index')->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        if ($ingredient->inventoryItems()->exists() || $ingredient->movements()->exists()) {
            return back()->with('error', 'Bahan baku yang memiliki stok atau riwayat tidak dapat dihapus. Nonaktifkan bahan baku tersebut.');
        }

        $id = $ingredient->id;
        $ingredient->delete();
        $this->audit->log('delete', 'inventory', 'ingredient', $id, [], [], auth()->id());

        return redirect()->route('admin.ingredients.index')->with('success', 'Bahan baku berhasil dihapus.');
    }
}
