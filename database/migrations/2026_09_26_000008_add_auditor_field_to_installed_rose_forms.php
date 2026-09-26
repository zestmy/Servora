<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every ROSE form already installed the "Auditor" header field.
 *
 * RoseTemplate now ships it, but a form is copied into the company when it
 * is installed and never read from the starter again — which is right for
 * items and points (a company edits those as its own) and wrong for a field
 * every ROSE form is expected to carry. So the one field is appended here,
 * once, to any ROSE form that does not already have an `auditor` field.
 * Header fields a company added itself are left exactly where they are.
 *
 * Audits already conducted are untouched: they hold their own copy of the
 * header at the time, and a past audit gaining an empty field would be odd.
 */
return new class extends Migration
{
    private const FIELD = ['key' => 'auditor', 'label' => 'Auditor', 'type' => 'auditor', 'required' => false];

    public function up(): void
    {
        $forms = DB::table('audit_templates')
            ->where('code', \App\Support\Audits\RoseTemplate::CODE)
            ->whereNull('deleted_at')
            ->get(['id', 'header_fields']);

        foreach ($forms as $form) {
            $fields = json_decode($form->header_fields ?? '[]', true) ?: [];

            $has = collect($fields)->contains(fn ($f) => ($f['type'] ?? null) === 'auditor' || ($f['key'] ?? null) === 'auditor');

            if ($has) {
                continue;
            }

            array_unshift($fields, self::FIELD);

            DB::table('audit_templates')->where('id', $form->id)->update([
                'header_fields' => json_encode(array_values($fields)),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Leave the field in place: removing it would also strip a field a
        // company may since have renamed and relied on.
    }
};
