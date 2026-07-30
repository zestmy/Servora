<?php

namespace App\Services;

use App\Models\LabelTemplate;
use App\Scopes\CompanyScope;
use App\Services\Labels\DefaultTemplates;

class LabelTemplateService
{
    /**
     * Give a company a default template for every label type it is missing.
     *
     * Idempotent, and safe to call on every visit to the label screens: a
     * company that has customised or deleted a default keeps its own choice,
     * because only types with NO default at all are created.
     *
     * @return int number of templates created
     */
    public function ensureDefaults(int $companyId): int
    {
        $existing = LabelTemplate::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->pluck('label_type')
            ->all();

        $created = 0;

        foreach (DefaultTemplates::all() as $type => $spec) {
            if (in_array($type, $existing, true)) {
                continue;
            }

            LabelTemplate::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $companyId,
                'name'       => $spec['name'],
                'label_type' => $type,
                'width_mm'   => DefaultTemplates::WIDTH_MM,
                'height_mm'  => DefaultTemplates::HEIGHT_MM,
                'engine'     => 'pdf',
                'layout'     => $spec['layout'],
                'is_default' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * The template to print a given label type with.
     *
     * Explicit override beats the type's default, which beats the company's
     * fallback template. Returns null only when a company has no templates
     * at all, which ensureDefaults() is there to prevent.
     */
    public function resolveFor(int $companyId, string $labelType, ?int $overrideId = null): ?LabelTemplate
    {
        $query = fn () => LabelTemplate::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId);

        if ($overrideId) {
            $override = $query()->find($overrideId);

            if ($override) {
                return $override;
            }
        }

        return $query()->where('label_type', $labelType)->where('is_default', true)->first()
            ?? $query()->where('label_type', $labelType)->first();
    }
}
