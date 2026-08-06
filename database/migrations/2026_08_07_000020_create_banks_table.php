<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The banks a company can pay salaries into — the IBG participating list,
 * seeded per company and editable from Settings.
 *
 * A TABLE rather than a constant because the list moves: banks merge, rename
 * and are added to IBG, and a company that banks with one that is missing
 * cannot wait for a deploy to record an account number. Seeded per company
 * rather than shared, so one company correcting a name — or retiring a bank it
 * will never use — does not reach into anybody else's picker.
 *
 * `employees.bank_name` still stores the NAME, not a foreign key. It is what
 * the payslip and the payment file print, what already sits in every existing
 * record, and what has to survive a bank being retired here — a payslip issued
 * last year must keep naming the bank that was paid, whatever the register
 * says today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60);

            /*
             * SWIFT/BIC. Nullable because a bank added by hand may not have one
             * to hand, and refusing the row over it would send the user back to
             * free text. 11 characters: BIC is 8 or 11.
             */
            $table->string('bic', 11)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The name is the stored value on an employee, so two rows sharing
            // one would make the picked bank ambiguous.
            $table->unique(['company_id', 'name'], 'banks_company_name_unique');
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
