<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the existing (previously unused) audit_logs table so it can back a
 * full, filterable audit trail:
 *  - outlet_id   → filter logs by branch (kept index-only, no FK, so a deleted
 *                  outlet never removes history).
 *  - user_name   → denormalised snapshot of the actor's name, so a log stays
 *                  readable even after the user is renamed or deleted.
 *  - guard       → which auth guard acted (web vs lms).
 *  - indexes     → company/date/user/event/outlet lookups the viewer relies on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'outlet_id')) {
                $table->unsignedBigInteger('outlet_id')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('audit_logs', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('audit_logs', 'guard')) {
                $table->string('guard', 20)->nullable()->after('user_name');
            }
        });

        // Indexes for the viewer's filter paths. Wrapped so a re-run is a no-op.
        Schema::table('audit_logs', function (Blueprint $table) {
            foreach ([
                'audit_logs_company_id_created_at_index' => ['company_id', 'created_at'],
                'audit_logs_company_id_user_id_index'    => ['company_id', 'user_id'],
                'audit_logs_company_id_event_index'      => ['company_id', 'event'],
                'audit_logs_company_id_outlet_id_index'  => ['company_id', 'outlet_id'],
            ] as $name => $columns) {
                if (! $this->indexExists('audit_logs', $name)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            foreach ([
                'audit_logs_company_id_created_at_index',
                'audit_logs_company_id_user_id_index',
                'audit_logs_company_id_event_index',
                'audit_logs_company_id_outlet_id_index',
            ] as $name) {
                if ($this->indexExists('audit_logs', $name)) {
                    $table->dropIndex($name);
                }
            }

            foreach (['guard', 'user_name', 'outlet_id'] as $col) {
                if (Schema::hasColumn('audit_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return count(Schema::getConnection()->select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$index]
        )) > 0;
    }
};
