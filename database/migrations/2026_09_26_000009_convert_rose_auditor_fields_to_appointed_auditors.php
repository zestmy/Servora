<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ROSE forms that already had their own auditor fields.
 *
 * The previous migration prepended a generic "Auditor" field to every ROSE
 * form. Production showed a form that had been customised before the
 * `auditor` type existed: "1st Auditor" and "2nd Auditor", typed as
 * `employee` because that was the only people-picker there was — so it now
 * carried three auditor fields, two of them offering the wrong people.
 *
 * On every ROSE form:
 *   - any `employee` header field whose label says auditor becomes `auditor`
 *     (its key, label and position are kept, so nothing else moves);
 *   - the generic `auditor` field added by the previous migration is removed
 *     when the form has auditor-typed fields of its own.
 *
 * Idempotent. Past audits keep their own copy of the header.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = DB::table('audit_templates')
            ->where('code', \App\Support\Audits\RoseTemplate::CODE)
            ->whereNull('deleted_at')
            ->get(['id', 'header_fields']);

        foreach ($forms as $form) {
            $fields  = json_decode($form->header_fields ?? '[]', true) ?: [];
            $changed = false;

            foreach ($fields as &$f) {
                if (($f['type'] ?? null) === 'employee' && stripos($f['label'] ?? '', 'auditor') !== false) {
                    $f['type'] = 'auditor';
                    $changed   = true;
                }
            }
            unset($f);

            $own = collect($fields)->filter(fn ($f) => ($f['type'] ?? null) === 'auditor' && ($f['key'] ?? null) !== 'auditor');

            if ($own->isNotEmpty()) {
                $before = count($fields);
                $fields = array_values(array_filter($fields, fn ($f) => ($f['key'] ?? null) !== 'auditor'));
                $changed = $changed || count($fields) !== $before;
            }

            if ($changed) {
                DB::table('audit_templates')->where('id', $form->id)->update([
                    'header_fields' => json_encode(array_values($fields)),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the previous shape offered the wrong people.
    }
};
