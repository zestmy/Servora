<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company's appointed auditors.
 *
 * An audit form's header can ask "who was the auditor / who accompanied the
 * audit", and the answer is not one of the audited outlet's staff — it is a
 * QA person from head office who appears at every outlet. So beside the
 * `employee` header field (the audited outlet's people) there is an
 * `auditor` header field, whose options are this list.
 *
 * A table rather than a flag on `employees`: appointment is an audit-module
 * decision made in audit settings, and the HR record should not grow a
 * column for every module that wants to pick from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_auditors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_auditors');
    }
};
