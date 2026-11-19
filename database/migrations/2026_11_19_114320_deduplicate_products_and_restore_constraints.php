<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = collect(Schema::getColumns('products'))->keyBy('name');

        if (! $columns->has('id')) {
            throw new RuntimeException('The products table does not have an id column.');
        }

        foreach (['sku', 'slug'] as $column) {
            $hasConflictingValues = DB::table('products')
                ->select($column)
                ->groupBy($column)
                ->havingRaw('COUNT(DISTINCT id) > 1')
                ->exists();

            if ($hasConflictingValues) {
                throw new RuntimeException("Cannot restore the unique {$column} constraint while different products share that value.");
            }
        }

        $duplicateIds = DB::table('products')
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id');

        DB::transaction(function () use ($duplicateIds): void {
            foreach ($duplicateIds as $productId) {
                $rows = DB::table('products')
                    ->where('id', $productId)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('created_at')
                    ->get();
                $latestProduct = (array) $rows->first();
                $comparisonColumns = array_flip(array_diff(array_keys($latestProduct), ['created_at', 'updated_at']));
                $latestProductValues = array_intersect_key($latestProduct, $comparisonColumns);

                foreach ($rows->skip(1) as $row) {
                    if (array_intersect_key((array) $row, $comparisonColumns) !== $latestProductValues) {
                        throw new RuntimeException("Product ID {$productId} has conflicting duplicate data.");
                    }
                }

                DB::table('products')->where('id', $productId)->delete();
                DB::table('products')->insert($latestProduct);
            }
        });

        $indexes = collect(Schema::getIndexes('products'));
        $hasIdPrimaryKey = $indexes
            ->contains(fn (array $index): bool => ($index['primary'] ?? false) && $index['columns'] === ['id']);

        if (! $hasIdPrimaryKey) {
            Schema::table('products', function (Blueprint $table): void {
                $table->primary('id');
            });
        }

        $idColumn = collect(Schema::getColumns('products'))->firstWhere('name', 'id');

        if (! $idColumn['auto_increment']) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('id')->autoIncrement()->change();
            });
        }

        $indexes = collect(Schema::getIndexes('products'));

        if (! $indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['sku'])) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unique('sku');
            });
        }

        $indexes = collect(Schema::getIndexes('products'));

        if (! $indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['slug'])) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unique('slug');
            });
        }

        $indexes = collect(Schema::getIndexes('products'));

        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['category_id'])) {
            Schema::table('products', function (Blueprint $table): void {
                $table->index('category_id');
            });
        }

        $indexes = collect(Schema::getIndexes('products'));

        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['is_active', 'is_available', 'sort_order'])) {
            Schema::table('products', function (Blueprint $table): void {
                $table->index(['is_active', 'is_available', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Product deduplication is irreversible; preserve the repaired data and constraints.
    }
};
