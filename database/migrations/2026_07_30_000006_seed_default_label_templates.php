<?php

use App\Services\LabelTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing company a starting set of label templates.
 *
 * ensureDefaults() is idempotent and only fills gaps, so a company that has
 * already customised a template keeps it. New companies get theirs lazily
 * when they first open the label screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = new LabelTemplateService();

        DB::table('companies')->orderBy('id')->pluck('id')
            ->each(fn ($companyId) => $service->ensureDefaults((int) $companyId));
    }

    public function down(): void
    {
        // Only the untouched stock templates — anything a company edited or
        // built themselves is theirs, and a rollback must not delete it.
        DB::table('label_templates')
            ->where('is_default', true)
            ->where('name', 'like', 'Standard %')
            ->delete();
    }
};
