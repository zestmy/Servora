<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('draft','submitted','sent','approved','partial','received','cancelled') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY COLUMN status ENUM('draft','sent','partial','received','cancelled') DEFAULT 'draft'");
    }
};
