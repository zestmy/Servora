<?php

namespace App\Services\Pdf;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\VideoShareToken;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Builds the Training Portal SOP PDFs.
 *
 * Extracted from SopPdfController so the synchronous category exports and the
 * queued full-catalogue export render through exactly one code path — the two
 * differ only in who calls this and what they do with the result. Anything
 * that changes the look of a category export has to change the queued one too,
 * and a second copy of the ordering joins below would have drifted.
 *
 * bulk() reports progress because the queued export is watched by a human. The
 * phases are honest about what can and cannot be measured: collecting and
 * preparing are per-recipe and give real numbers, but dompdf's render is a
 * single opaque call that holds the whole document, so it reports as a phase
 * rather than a percentage and the UI shows it as indeterminate.
 */
class SopExportBuilder
{
    public const PHASE_COLLECTING = 'collecting';
    public const PHASE_PREPARING  = 'preparing';
    public const PHASE_RENDERING  = 'rendering';

    /** Where the per-recipe preparation loop starts and stops on the bar. */
    private const PREPARE_FROM = 8;
    private const PREPARE_TO   = 70;

    private const EAGER = [
        'steps', 'images', 'lines.uom', 'yieldUom',
        'lines.ingredient.recipeUom', 'lines.ingredient.secondaryRecipeUom', 'lines.ingredient.uomConversions',
    ];

    public function __construct(private PdfImage $images)
    {
    }

    /**
     * One recipe's SOP.
     *
     * @return array{pdf: \Barryvdh\DomPDF\PDF, filename: string}
     */
    public function single($user, int $recipeId, array $traineeOutletIds = []): array
    {
        $company = Company::find($user->company_id);

        $recipe = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->visibleToOutlets($traineeOutletIds)
            ->with(self::EAGER)
            ->findOrFail($recipeId);

        // Prep-item photos (type 'presentation') fill the dine-in slot —
        // a recipe never carries both types.
        $dineInBase64   = $this->imagesToBase64($recipe->images->whereIn('type', ['dine_in', 'presentation'])->values());
        $takeawayBase64 = $this->imagesToBase64($recipe->images->where('type', 'takeaway')->values());
        $logoBase64     = $this->images->logo($company?->logo);
        $stepImagesBase64 = $this->stepImagesToBase64($recipe->steps);

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'Company';
        $videoQr    = $recipe->video_url ? $this->generateVideoQr($recipe->id, $company->id) : null;

        $recentActivity = $this->recentActivityByRecipe([$recipe->id])->get($recipe->id, collect());

        $pdf = Pdf::loadView('pdf.sop-single', compact(
            'recipe', 'company', 'dineInBase64', 'takeawayBase64', 'logoBase64', 'stepImagesBase64', 'exportedBy', 'brandName', 'videoQr', 'recentActivity'
        ))->setPaper('a4', 'portrait');

        return [
            'pdf'      => $pdf,
            'filename' => $this->safeFilename("SOP-{$recipe->code}-{$recipe->name}.pdf"),
        ];
    }

    /**
     * A filtered set of SOPs, or the whole catalogue when $filters is empty.
     *
     * $filters accepts 'prep' (bool), 'prep_category' (id), 'category' (name)
     * and 'category_group' (root category id) — the four the Training Portal
     * links offer.
     *
     * $onProgress receives (int $percent, string $label, string $phase).
     *
     * @return array{pdf: \Barryvdh\DomPDF\PDF, filename: string, recipeCount: int}
     */
    public function bulk($user, array $filters = [], array $traineeOutletIds = [], ?callable $onProgress = null): array
    {
        $progress = $onProgress ?? fn () => null;

        $progress(3, 'Collecting recipes', self::PHASE_COLLECTING);

        $company = Company::find($user->company_id);

        // Optional prep-only export — only prep-item SOPs, skipping menu recipes.
        $prepOnly = (bool) ($filters['prep'] ?? false);

        // Optional prep category filter — a recipe-category id (prep items share the
        // recipes' menu categories); exports only prep-item SOPs in that category
        // plus its sub-categories. Implies prep-only.
        $prepCategoryId = (int) ($filters['prep_category'] ?? 0) ?: null;
        $prepCategoryNames = null;
        $prepCategoryLabel = null;
        if ($prepCategoryId) {
            $prepCat = RecipeCategory::outletScope()
                ->where('company_id', $user->company_id)
                ->with('children')
                ->find($prepCategoryId);
            if ($prepCat) {
                $prepCategoryNames = collect([$prepCat->name])->merge($prepCat->children->pluck('name'))->all();
                $prepCategoryLabel = $prepCat->name;
            }
            $prepOnly = true;
        }

        // Optional category filter — when set, export only recipe SOPs in that category.
        $category = trim((string) ($filters['category'] ?? '')) ?: null;

        // Optional top-tier group filter (e.g. "All Food") — a root recipe-category id;
        // exports the root category plus all of its sub-categories.
        $categoryGroupId = (int) ($filters['category_group'] ?? 0) ?: null;
        $groupNames = null;
        $groupLabel = null;
        if ($categoryGroupId) {
            $root = RecipeCategory::outletScope()->where('company_id', $user->company_id)->with('children')->find($categoryGroupId);
            if ($root) {
                $groupNames = collect([$root->name])->merge($root->children->pluck('name'))->all();
                $groupLabel = $root->name;
            }
        }
        $isFiltered = $category !== null || $groupNames !== null;

        // Shared LMS visibility scope (company + active + LMS-enabled + trainee outlets).
        $applyScope = function ($q) use ($user, $traineeOutletIds) {
            return $q->where('recipes.company_id', $user->company_id)
                ->where('recipes.is_active', true)
                ->where('recipes.exclude_from_lms', false)
                ->visibleToOutlets($traineeOutletIds);
        };

        // Non-prep recipes — ordered EXACTLY like the Recipes list (category hierarchy:
        // root sort/name → sub sort/name → menu_sort_order → name) so the exported PDF
        // matches the on-screen ordering instead of a flat name sort.
        $nonPrep = $prepOnly ? collect() : $this->orderByCategoryHierarchy(
            $applyScope(Recipe::query()->where('recipes.is_prep', false))
                ->when($category, fn ($q) => $q->where('recipes.category', $category))
                ->when($groupNames, fn ($q) => $q->whereIn('recipes.category', $groupNames))
        )->with(self::EAGER)->get();

        // Prep items follow, ordered by the same menu-category hierarchy (prep items
        // share the recipes' menu categories). Skipped when a recipe-category filter
        // is active — the Training Portal offers dedicated prep-category exports.
        $prep = ($isFiltered && ! $prepOnly)
            ? collect()
            : $this->orderByCategoryHierarchy(
                $applyScope(Recipe::query()->where('recipes.is_prep', true))
                    ->when($prepCategoryNames, fn ($q) => $q->whereIn('recipes.category', $prepCategoryNames))
            )->with(self::EAGER)->get();

        $recipes = $nonPrep->concat($prep);
        $total   = $recipes->count();

        $grouped = $recipes->groupBy(fn ($r) => $r->is_prep
            ? 'Prep — ' . ($r->category ?? 'Uncategorised')
            : ($r->category ?? 'Uncategorised'));

        $logoBase64 = $this->images->logo($company?->logo);

        $progress(self::PREPARE_FROM, $this->plural($total, 'recipe') . ' to prepare', self::PHASE_PREPARING);

        // Pre-compute base64 images and QR codes for all recipes
        $recipeImages = [];
        $recipeQrs    = [];
        $recipeStepImages = [];
        $done = 0;
        foreach ($recipes as $recipe) {
            $recipeImages[$recipe->id] = [
                'dine_in'  => $this->imagesToBase64($recipe->images->whereIn('type', ['dine_in', 'presentation'])->values()),
                'takeaway' => $this->imagesToBase64($recipe->images->where('type', 'takeaway')->values()),
            ];
            $recipeQrs[$recipe->id] = $recipe->video_url ? $this->generateVideoQr($recipe->id, $company->id) : null;
            $recipeStepImages[$recipe->id] = $this->stepImagesToBase64($recipe->steps);

            $done++;
            if ($total > 0) {
                $pct = self::PREPARE_FROM + (int) round(($done / $total) * (self::PREPARE_TO - self::PREPARE_FROM));
                $progress($pct, "Preparing images — {$done} of {$total}", self::PHASE_PREPARING);
            }
        }

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'SOP';

        $progress(self::PREPARE_TO + 2, 'Loading update history', self::PHASE_PREPARING);

        $recipeActivity = $this->recentActivityByRecipe($recipes->pluck('id')->all());

        // dompdf from here: one call that builds a frame per DOM node for the
        // whole document and only serialises at the end, so there is nothing
        // honest to report until it returns.
        $progress(self::PREPARE_TO + 5, 'Rendering ' . $this->plural($total, 'SOP') . ' into a PDF', self::PHASE_RENDERING);

        $pdf = Pdf::loadView('pdf.sop-all', compact(
            'grouped', 'company', 'logoBase64', 'recipeImages', 'recipeQrs', 'recipeStepImages', 'exportedBy', 'brandName', 'recipeActivity'
        ))->setPaper('a4', 'portrait');

        $fileLabel = $prepOnly
            ? ($prepCategoryLabel ? "Prep-{$prepCategoryLabel}" : 'Prep-Items')
            : ($groupLabel
                ? "All-{$groupLabel}"
                : ($category ?: 'Training-SOPs'));

        return [
            'pdf'         => $pdf,
            'filename'    => $this->safeFilename("{$brandName}-{$fileLabel}.pdf"),
            'recipeCount' => $total,
        ];
    }

    /** Human label for a set of filters, for the export list. */
    public function describe($user, array $filters = []): string
    {
        if ($id = (int) ($filters['prep_category'] ?? 0)) {
            $name = RecipeCategory::outletScope()->where('company_id', $user->company_id)->whereKey($id)->value('name');
            return $name ? "Prep — {$name}" : 'Prep items';
        }

        if ($filters['prep'] ?? false) {
            return 'All prep items';
        }

        if ($id = (int) ($filters['category_group'] ?? 0)) {
            $name = RecipeCategory::outletScope()->where('company_id', $user->company_id)->whereKey($id)->value('name');
            return $name ? "All {$name}" : 'Category group';
        }

        if ($name = trim((string) ($filters['category'] ?? ''))) {
            return $name;
        }

        return 'All SOPs';
    }

    /**
     * The Recipes-list ordering, as a join set.
     *
     * Scope guard: kitchen categories share this table and can reuse a
     * name, which would duplicate rows on a name-based join.
     */
    private function orderByCategoryHierarchy($query)
    {
        return $query
            ->leftJoin('recipe_categories as rc', function ($join) {
                $join->on('rc.name', '=', 'recipes.category')
                     ->on('rc.company_id', '=', 'recipes.company_id')
                     ->where('rc.scope', RecipeCategory::SCOPE_OUTLET)
                     ->whereNull('rc.deleted_at')
                     ->whereNotNull('rc.parent_id');
            })
            ->leftJoin('recipe_categories as rc_root', function ($join) {
                $join->on('rc_root.name', '=', 'recipes.category')
                     ->on('rc_root.company_id', '=', 'recipes.company_id')
                     ->where('rc_root.scope', RecipeCategory::SCOPE_OUTLET)
                     ->whereNull('rc_root.deleted_at')
                     ->whereNull('rc_root.parent_id');
            })
            ->leftJoin('recipe_categories as rcp', 'rcp.id', '=', 'rc.parent_id')
            ->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) IS NULL')
            ->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) ASC')
            ->orderByRaw('COALESCE(rcp.name, rc_root.name, rc.name) ASC')
            ->orderByRaw('COALESCE(rc.sort_order, rc_root.sort_order) ASC')
            ->orderByRaw('COALESCE(rc.name, rc_root.name) ASC')
            ->orderBy('recipes.menu_sort_order')
            ->orderBy('recipes.name')
            ->select('recipes.*');
    }

    private function plural(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }

    /**
     * Latest audit entries per recipe (newest first, max $limit each) for the
     * "Latest 5 Update Activity" section. One query for the whole recipe set —
     * a per-recipe query would N+1 on bulk exports. Returns a collection keyed
     * by recipe id.
     */
    private function recentActivityByRecipe(array $recipeIds, int $limit = 5)
    {
        if (empty($recipeIds)) {
            return collect();
        }

        return AuditLog::with('user:id,name')
            ->select(['id', 'user_id', 'user_name', 'event', 'auditable_id', 'old_values', 'new_values', 'created_at'])
            ->where('auditable_type', Recipe::class)
            ->whereIn('auditable_id', $recipeIds)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()
            ->groupBy('auditable_id')
            ->map(fn ($logs) => $logs->take($limit)->values());
    }

    /**
     * Strip characters Symfony rejects in Content-Disposition filenames.
     */
    private function safeFilename(string $name): string
    {
        return str_replace(['/', '\\'], '-', $name);
    }

    /**
     * Convert step images to base64, keyed by step id.
     */
    private function stepImagesToBase64($steps): array
    {
        $result = [];
        foreach ($steps as $step) {
            if (! $step->image_path) continue;
            if ($uri = $this->images->photo($step->image_path)) {
                $result[$step->id] = $uri;
            }
        }
        return $result;
    }

    private function imagesToBase64($images): array
    {
        $result = [];
        foreach ($images as $img) {
            if ($uri = $this->images->photo($img->file_path)) {
                $result[] = $uri;
            }
        }
        return $result;
    }

    private function generateVideoQr(int $recipeId, int $companyId): ?string
    {
        $share = VideoShareToken::forRecipe($recipeId, $companyId);

        // Always use main domain for QR URL (not subdomain)
        $domain = config('app.domain');
        if ($domain) {
            $url = 'https://' . $domain . '/v/' . $share->token;
        } else {
            $url = route('video.share', $share->token);
        }

        return $this->generateQr($url);
    }

    private function generateQr(?string $url): ?string
    {
        if (! $url) return null;
        try {
            $options = new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'scale'           => 5,
                'quietzoneSize'   => 1,
            ]);
            return (new QRCode($options))->render($url);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
