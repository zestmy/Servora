<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which paper/form a PrintNode job should ask for.
 *
 * PrintNode hands the job to the Windows driver on the client machine. With
 * no paper named, that driver uses its own default — and if the default is
 * not the label stock, the page is rotated or scaled to fit before it ever
 * reaches the printer. Naming it per job removes the guess.
 *
 * Nullable: leaving it unset keeps the previous behaviour of accepting the
 * driver default, which is correct when the default is already right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->string('printnode_paper')->nullable()->after('printnode_printer_id');
        });
    }

    public function down(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->dropColumn('printnode_paper');
        });
    }
};
