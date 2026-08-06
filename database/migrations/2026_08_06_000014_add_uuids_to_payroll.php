<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * UUIDs on payroll runs and lines, for the URLs.
 *
 * These routes were never open — `can:hr.payroll`, a company check and a
 * line-belongs-to-this-run check all stand in front of them, and an outsider
 * gets a 403 whatever id they try. What sequential ids gave away was
 * ENUMERABILITY: anyone who could reach one payslip could walk the whole set
 * by counting, and a link pasted into a chat or an email quietly advertises
 * how many people are on the payroll.
 *
 * The UUID is defence in depth, NOT the access control. The permission checks
 * stay exactly where they are — a guessable id was never what was holding the
 * door shut, and an unguessable one must not be mistaken for a lock.
 *
 * The integer primary key is untouched: foreign keys, joins and the existing
 * unique constraints all keep working, and only the route key changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill before the unique index, or every existing row collides on
        // null in strict mode and the migration dies half way.
        foreach (['payroll_runs', 'payroll_run_lines'] as $table) {
            DB::table($table)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
