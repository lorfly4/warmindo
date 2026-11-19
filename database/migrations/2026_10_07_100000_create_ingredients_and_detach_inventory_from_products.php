<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_movements')->whereNull('warehouse_id')->orderBy('id')->chunk(500, function ($movements): void {
            foreach ($movements as $movement) {
                DB::table('product_stock_movements')->insert([
                    'product_id' => $movement->product_id,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'unit' => $movement->unit ?? 'pcs',
                    'before' => $movement->before,
                    'after' => $movement->after,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'actor_id' => $movement->actor_id,
                    'note' => $movement->note,
                    'created_at' => $movement->created_at,
                ]);
            }
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $productIds = DB::table('inventory_items')->select('product_id')
            ->union(DB::table('inventory_movements')->whereNotNull('warehouse_id')->select('product_id'))
            ->pluck('product_id');

        foreach ($productIds as $productId) {
            $product = DB::table('products')->where('id', $productId)->first();

            if (! $product) {
                continue;
            }

            $ingredientId = DB::table('ingredients')->insertGetId([
                'name' => $product->name,
                'sku' => $product->sku ? 'LEGACY-'.$product->sku : null,
                'description' => 'Dipindahkan dari inventori produk lama',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_items')->where('product_id', $productId)->update(['ingredient_id' => $ingredientId]);
            DB::table('inventory_movements')
                ->where('product_id', $productId)
                ->whereNotNull('warehouse_id')
                ->update(['ingredient_id' => $ingredientId]);
        }

        DB::table('inventory_movements')->whereNull('warehouse_id')->delete();

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'product_id']);
            $table->dropIndex(['product_id', 'quantity']);
            $table->dropConstrainedForeignId('product_id');
            $table->foreignId('ingredient_id')->nullable(false)->change();
            $table->unique(['warehouse_id', 'ingredient_id']);
            $table->index(['ingredient_id', 'quantity']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'created_at']);
            $table->dropConstrainedForeignId('product_id');
            $table->index(['ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir agar data bahan baku mandiri tidak dihapus.');
    }
};
