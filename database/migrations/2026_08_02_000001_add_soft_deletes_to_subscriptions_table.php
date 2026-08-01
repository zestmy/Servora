<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Companies are soft-deleted, so until now a subscription outlived its
     * company as an orphan row: invisible company, live-looking subscription.
     * Subscriptions now soft-delete alongside the company (see Company::booted)
     * and restore with it, which keeps the delete flow's promise that a
     * deleted company is fully restorable.
     *
     * deleted_with_company marks the rows the cascade took, so a restore brings
     * back exactly those and leaves a subscription that was deleted on its own
     * beforehand deleted. It is a flag rather than a deleted_at comparison
     * because deleted_at is second-precision — deleting a subscription and then
     * its company within the same second gives both rows an identical stamp.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->softDeletes();
            $table->boolean('deleted_with_company')->default(false)->after('deleted_at');
        });

        // Existing orphans: adopt them into the cascade so they come back if
        // their company is ever restored.
        DB::table('subscriptions')
            ->join('companies', 'companies.id', '=', 'subscriptions.company_id')
            ->whereNotNull('companies.deleted_at')
            ->whereNull('subscriptions.deleted_at')
            ->update([
                'subscriptions.deleted_at'           => DB::raw('companies.deleted_at'),
                'subscriptions.deleted_with_company' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('deleted_with_company');
            $table->dropSoftDeletes();
        });
    }
};
