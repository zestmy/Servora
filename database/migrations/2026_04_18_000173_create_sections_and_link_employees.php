<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the employee "department" (FOH / BOH / ...) away from the existing
 * `departments` table, which is already used for PO receiver / cost-tracking
 * units (Kitchen, Bar, etc.). Employees now link to a dedicated `sections`
 * table instead. Existing employee → department links are migrated over,
 * and FOH / BOH are seeded in the new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) New sections table, scoped per company.
        if (! Schema::hasTable('sections')) {
            Schema::create('sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'name']);
            });
        }

        // 2) Seed FOH + BOH for every company (only when missing).
        $companyIds = DB::table('companies')->pluck('id');
        foreach ($companyIds as $companyId) {
            foreach (['FOH', 'BOH'] as $idx => $name) {
                $exists = DB::table('sections')
                    ->where('company_id', $companyId)
                    ->whereRaw('UPPER(name) = ?', [$name])
                    ->exists();
                if (! $exists) {
                    DB::table('sections')->insert([
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

        // 3) Add section_id FK to employees.
        if (! Schema::hasColumn('employees', 'section_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('section_id')->nullable()->after('designation')
                      ->constrained()->nullOnDelete();
            });
        }

        // 4) Migrate existing employees.department_id → section_id.
        if (Schema::hasColumn('employees', 'department_id')) {
            $rows = DB::table('employees')
                ->whereNotNull('department_id')
                ->get(['id', 'company_id', 'department_id']);

            foreach ($rows as $emp) {
                $dept = DB::table('departments')->where('id', $emp->department_id)->first();
                if (! $dept) continue;

                $name = trim((string) $dept->name);
                if ($name === '') continue;

                // Find or create the matching section for this company.
                $section = DB::table('sections')
                    ->where('company_id', $emp->company_id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();

                if (! $section) {
                    $sectionId = DB::table('sections')->insertGetId([
                        'company_id' => $emp->company_id,
                        'name'       => $name,
                        'sort_order' => 99,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $sectionId = $section->id;
                }

                DB::table('employees')->where('id', $emp->id)->update(['section_id' => $sectionId]);
            }

            // 5) Drop the now-stale FK to departments.
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }

    public function down(): void
    {
        // Re-add the old column (nullable) so data can be rolled back on revert.
        if (! Schema::hasColumn('employees', 'department_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('designation')
                      ->constrained()->nullOnDelete();
            });
        }

        // Best-effort: repopulate department_id from the matching department name
        // so reverting doesn't silently lose the link.
        $rows = DB::table('employees')
            ->join('sections', 'employees.section_id', '=', 'sections.id')
            ->select('employees.id', 'employees.company_id', 'sections.name')
            ->get();

        foreach ($rows as $r) {
            $dept = DB::table('departments')
                ->where('company_id', $r->company_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($r->name)])
                ->first();
            if ($dept) {
                DB::table('employees')->where('id', $r->id)->update(['department_id' => $dept->id]);
            }
        }

        if (Schema::hasColumn('employees', 'section_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('section_id');
            });
        }

        Schema::dropIfExists('sections');
    }
};
