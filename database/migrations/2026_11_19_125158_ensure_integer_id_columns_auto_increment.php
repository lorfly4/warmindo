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
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $databaseName = DB::connection()->getDatabaseName();

        foreach (Schema::getTables() as $tableMetadata) {
            if (($tableMetadata['schema'] ?? $databaseName) !== $databaseName) {
                continue;
            }

            $tableName = $tableMetadata['name'];
            $idColumn = collect(Schema::getColumns($tableName))->firstWhere('name', 'id');

            if ($idColumn === null || $idColumn['auto_increment']) {
                continue;
            }

            $typeName = strtolower($idColumn['type_name']);

            if (! in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
                continue;
            }

            if ($idColumn['nullable']) {
                throw new RuntimeException("Cannot enable auto-increment for nullable ID column on {$tableName}.");
            }

            $hasIdIndex = collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'id');

            if (! $hasIdIndex) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->index('id', 'id_auto_increment_index');
                });
            }

            $isUnsigned = str_contains(strtolower($idColumn['type']), 'unsigned');

            Schema::table($tableName, function (Blueprint $table) use ($typeName, $isUnsigned): void {
                $column = match ($typeName) {
                    'tinyint' => $table->tinyInteger('id'),
                    'smallint' => $table->smallInteger('id'),
                    'mediumint' => $table->mediumInteger('id'),
                    'int', 'integer' => $table->integer('id'),
                    'bigint' => $table->bigInteger('id'),
                };

                if ($isUnsigned) {
                    $column->unsigned();
                }

                $column->autoIncrement()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep generated IDs and their indexes intact during rollback.
    }
};
