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
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $idColumn = collect(Schema::getColumns('audit_logs'))->firstWhere('name', 'id');

        if ($idColumn === null) {
            throw new RuntimeException('The audit_logs table does not have an id column.');
        }

        if ($idColumn['auto_increment']) {
            return;
        }

        $hasIdPrimaryKey = collect(Schema::getIndexes('audit_logs'))
            ->contains(fn (array $index): bool => $index['primary'] && $index['columns'] === ['id']);

        if (! $hasIdPrimaryKey && DB::table('audit_logs')
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot add an audit log primary key while duplicate IDs exist.');
        }

        if (! $hasIdPrimaryKey) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->primary('id');
            });
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->autoIncrement()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep generated audit IDs and their primary key intact during rollback.
    }
};
