<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Label printing — core tables: templates, per-company settings, printers.
 *
 * See docs/label-printing-plan.md. Transport is browser kiosk printing; the
 * printnode_* columns exist so switching drivers later is a config change
 * rather than a migration, and are unused today.
 *
 * label_type and storage_state are plain strings, not MySQL enums, so adding
 * a label type later doesn't need an ALTER on a table with data in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label_type', 20);
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            $table->string('engine', 10)->default('pdf');
            // Positioned field list — see LabelTemplate::layout docblock.
            $table->json('layout');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'label_type'], 'label_templates_company_type_idx');
        });

        Schema::create('label_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            // 'eod' rounds day/week/month lives to 23:59; 'exact' never rounds.
            // Sub-day units are always exact regardless — see ShelfLifeService.
            $table->string('use_by_rounding', 10)->default('eod');
            $table->foreignId('default_template_id')->nullable()
                ->constrained('label_templates')->nullOnDelete();
            $table->string('footer_text')->nullable();
            // Unused under the browser driver. MUST be an encrypted cast when
            // it is finally used — text, because ciphertext is long.
            $table->text('printnode_api_key')->nullable();
            $table->timestamps();
        });

        Schema::create('label_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 20)->default('browser');
            $table->string('printnode_printer_id')->nullable();
            $table->foreignId('default_template_id')->nullable()
                ->constrained('label_templates')->nullOnDelete();
            // Physical label stock loaded in this printer.
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'outlet_id'], 'label_printers_company_outlet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_printers');
        Schema::dropIfExists('label_settings');
        Schema::dropIfExists('label_templates');
    }
};
