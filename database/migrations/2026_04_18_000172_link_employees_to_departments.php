<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promote the free-text `employees.department` column (added in 171) to
 * a proper `department_id` FK so OT Claims and the coming duty roster
 * can filter by department without dealing with spelling variants.
 * Also seeds FOH and BOH for every company so filters have something
 * sensible out of the box.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Seed FOH and BOH for every company (only if missing).
        $companyIds = DB::table('companies')->pluck('id');
        foreach ($companyIds as $companyId) {
            foreach (['FOH', 'BOH'] as $idx => $name) {
                $exists = DB::table('departments')
                    ->where('company_id', $companyId)
                    ->whereRaw('UPPER(name) = ?', [$name])
                    ->exists();
                if (! $exists) {
                    DB::table('departments')->insert([
                        'company_id' => $companyId,
                        'name'       => $name,
                        'sort_order' => $idx,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 2) Add the FK column.
        if (! Schema::hasColumn('employees', 'department_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('designation')
                      ->constrained()->nullOnDelete();
            });
        }

        // 3) Backfill from the legacy text column where the name matches.
        if (Schema::hasColumn('employees', 'department')) {
            $rows = DB::table('employees')->whereNotNull('department')->get(['id', 'company_id', 'department']);
            foreach ($rows as $emp) {
                $name = trim((string) $emp->department);
                if ($name === '') continue;

                $dept = DB::table('departments')
                    ->where('company_id', $emp->company_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();

                if (! $dept) {
                    // Auto-create to preserve existing data.
                    $id = DB::table('departments')->insertGetId([
                        'company_id' => $emp->company_id,
                        'name'       => $name,
                        'sort_order' => 99,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $deptId = $id;
                } else {
                    $deptId = $dept->id;
                }

                DB::table('employees')->where('id', $emp->id)->update(['department_id' => $deptId]);
            }

            // 4) Drop the now-redundant text column.
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'department')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('department')->nullable()->after('designation');
            });
        }

        // Best-effort repopulate the text column from the FK so the revert
        // is not destructive.
        if (Schema::hasColumn('employees', 'department_id')) {
            $rows = DB::table('employees')
                ->join('departments', 'employees.department_id', '=', 'departments.id')
                ->select('employees.id', 'departments.name')
                ->get();
            foreach ($rows as $r) {
                DB::table('employees')->where('id', $r->id)->update(['department' => $r->name]);
            }

            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        // FOH / BOH seed rows are left in place — departments may have been
        // linked to other records (PO receivers etc.) and removing them
        // unconditionally would be destructive.
    }
};
