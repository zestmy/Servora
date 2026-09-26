<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photographs on a corrective action, of two kinds.
 *
 *   evidence      the OWNER's: what the rectification looks like now. Taken
 *                 by the Manager or Chef on the Staff Portal, or attached by
 *                 the auditor on their behalf.
 *   verification  the AUDITOR's: what they saw when they came to confirm it.
 *
 * Kept apart because the two-step close depends on it: the person who did
 * the work and the person who signs it off must not be able to overwrite
 * each other's picture. Replaces the single `evidence_path` slot, whose one
 * photo is carried across as an `evidence` row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrective_action_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrective_action_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12)->default('evidence');           // evidence | verification
            $table->string('file_path');                               // public disk
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['corrective_action_id', 'kind']);
        });

        $now = now();

        foreach (DB::table('corrective_actions')->whereNotNull('evidence_path')->get(['id', 'evidence_path', 'created_at']) as $row) {
            DB::table('corrective_action_photos')->insert([
                'corrective_action_id' => $row->id,
                'kind'                 => 'evidence',
                'file_path'            => $row->evidence_path,
                'created_at'           => $row->created_at ?? $now,
                'updated_at'           => $now,
            ]);
        }

        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->dropColumn('evidence_path');
        });
    }

    public function down(): void
    {
        Schema::table('corrective_actions', function (Blueprint $table) {
            $table->string('evidence_path')->nullable()->after('completion_note');
        });

        foreach (DB::table('corrective_action_photos')->where('kind', 'evidence')->orderBy('id')->get(['corrective_action_id', 'file_path']) as $row) {
            DB::table('corrective_actions')->where('id', $row->corrective_action_id)->whereNull('evidence_path')
                ->update(['evidence_path' => $row->file_path]);
        }

        Schema::dropIfExists('corrective_action_photos');
    }
};
