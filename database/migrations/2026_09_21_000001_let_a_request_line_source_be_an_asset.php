<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 'asset' joins the source enum.
 *
 * The migration that let a request line name an asset added `asset_id` but
 * left this column at ('supplier','kitchen'), while the form writes
 * PurchaseRequestLine::SOURCE_ASSET onto every asset line — so saving a
 * request with an asset on it was a 500 on MySQL ("Data truncated for column
 * 'source'"). The SQLite test database never caught it: SQLite cannot attach a
 * CHECK constraint through ALTER TABLE ADD COLUMN, so the column was created
 * there as a plain varchar that accepts anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema builder rather than raw "ALTER TABLE ... MODIFY COLUMN": that syntax is
        // MySQL-only, so this migration could not build the SQLite database the test suite
        // runs against. change() emits ENUM on MySQL and a CHECK-constrained varchar on
        // SQLite, so both drivers end up enforcing the same set.
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->enum('source', ['supplier', 'kitchen', 'asset'])
                ->default('supplier')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->enum('source', ['supplier', 'kitchen'])
                ->default('supplier')->change();
        });
    }
};
