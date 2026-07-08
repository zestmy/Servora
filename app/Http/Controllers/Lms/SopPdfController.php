<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\VideoShareToken;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SopPdfController extends Controller
{
    public function single(int $id)
    {
        $isLmsTrainee = Auth::guard('lms')->check();
        $user = $isLmsTrainee
            ? Auth::guard('lms')->user()
            : Auth::user();

        $company = Company::find($user->company_id);

        $traineeOutletId = $isLmsTrainee ? $user->outlet_id : null;

        $recipe = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->when($traineeOutletId, fn ($q) => $q->where(function ($q) use ($traineeOutletId) {
                $q->whereDoesntHave('outlets')
                  ->orWhereHas('outlets', fn ($o) => $o->where('outlets.id', $traineeOutletId));
            }))
            ->with([
                'steps', 'images', 'lines.uom', 'yieldUom',
                'lines.ingredient.recipeUom', 'lines.ingredient.secondaryRecipeUom', 'lines.ingredient.uomConversions',
            ])
            ->findOrFail($id);

        // Prep-item photos (type 'presentation') fill the dine-in slot —
        // a recipe never carries both types.
        $dineInImages   = $recipe->images->whereIn('type', ['dine_in', 'presentation'])->values();
        $takeawayImages = $recipe->images->where('type', 'takeaway')->values();

        // Convert images to base64 for DomPDF
        $dineInBase64   = $this->imagesToBase64($dineInImages);
        $takeawayBase64 = $this->imagesToBase64($takeawayImages);
        $logoBase64     = $this->logoToBase64($company);
        $stepImagesBase64 = $this->stepImagesToBase64($recipe->steps);

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'Company';
        $videoQr    = $recipe->video_url ? $this->generateVideoQr($recipe->id, $company->id) : null;

        $pdf = Pdf::loadView('pdf.sop-single', compact(
            'recipe', 'company', 'dineInBase64', 'takeawayBase64', 'logoBase64', 'stepImagesBase64', 'exportedBy', 'brandName', 'videoQr'
        ))->setPaper('a4', 'portrait');

        return $pdf->download($this->safeFilename("SOP-{$recipe->code}-{$recipe->name}.pdf"));
    }

    public function all()
    {
        $isLmsTrainee = Auth::guard('lms')->check();
        $user = $isLmsTrainee
            ? Auth::guard('lms')->user()
            : Auth::user();

        $company = Company::find($user->company_id);

        $traineeOutletId = $isLmsTrainee ? $user->outlet_id : null;

        // Optional prep-only export — only prep-item SOPs, skipping menu recipes.
        $prepOnly = request()->boolean('prep');

        // Optional prep sub-category filter — an ingredient-category id; exports only
        // prep-item SOPs in that category plus its sub-categories. Implies prep-only.
        $prepCategoryId = (int) request('prep_category') ?: null;
        $prepCategoryIds = null;
        $prepCategoryLabel = null;
        if ($prepCategoryId) {
            $prepCat = IngredientCategory::where('company_id', $user->company_id)
                ->with('children')
                ->find($prepCategoryId);
            if ($prepCat) {
                $prepCategoryIds = collect([$prepCat->id])->merge($prepCat->children->pluck('id'))->all();
                $prepCategoryLabel = $prepCat->name;
            }
            $prepOnly = true;
        }

        // Optional category filter — when set, export only recipe SOPs in that category.
        $category = trim((string) request('category')) ?: null;

        // Optional top-tier group filter (e.g. "All Food") — a root recipe-category id;
        // exports the root category plus all of its sub-categories.
        $categoryGroupId = (int) request('category_group') ?: null;
        $groupNames = null;
        $groupLabel = null;
        if ($categoryGroupId) {
            $root = RecipeCategory::where('company_id', $user->company_id)->with('children')->find($categoryGroupId);
            if ($root) {
                $groupNames = collect([$root->name])->merge($root->children->pluck('name'))->all();
                $groupLabel = $root->name;
            }
        }
        $isFiltered = $category !== null || $groupNames !== null;

        $eager = [
            'steps', 'images', 'lines.uom', 'yieldUom', 'ingredientCategory',
            'lines.ingredient.recipeUom', 'lines.ingredient.secondaryRecipeUom', 'lines.ingredient.uomConversions',
        ];

        // Shared LMS visibility scope (company + active + LMS-enabled + trainee outlet).
        $applyScope = function ($q) use ($user, $traineeOutletId) {
            return $q->where('recipes.company_id', $user->company_id)
                ->where('recipes.is_active', true)
                ->where('recipes.exclude_from_lms', false)
                ->when($traineeOutletId, fn ($q2) => $q2->where(function ($w) use ($traineeOutletId) {
                    $w->whereDoesntHave('outlets')
                      ->orWhereHas('outlets', fn ($o) => $o->where('outlets.id', $traineeOutletId));
                }));
        };

        // Non-prep recipes — ordered EXACTLY like the Recipes list (category hierarchy:
        // root sort/name → sub sort/name → menu_sort_order → name) so the exported PDF
        // matches the on-screen ordering instead of a flat name sort.
        $nonPrep = $prepOnly ? collect() : $applyScope(Recipe::query()->where('recipes.is_prep', false))
            ->when($category, fn ($q) => $q->where('recipes.category', $category))
            ->when($groupNames, fn ($q) => $q->whereIn('recipes.category', $groupNames))
            ->leftJoin('recipe_categories as rc', function ($join) {
                $join->on('rc.name', '=', 'recipes.category')
                     ->on('rc.company_id', '=', 'recipes.company_id')
                     ->whereNull('rc.deleted_at')
                     ->whereNotNull('rc.parent_id');
            })
            ->leftJoin('recipe_categories as rc_root', function ($join) {
                $join->on('rc_root.name', '=', 'recipes.category')
                     ->on('rc_root.company_id', '=', 'recipes.company_id')
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
            ->select('recipes.*')
            ->with($eager)
            ->get();

        // Prep items follow, ordered by ingredient-category hierarchy. Skipped when a
        // recipe-category filter is active (prep items have no menu category).
        $prep = ($isFiltered && ! $prepOnly)
            ? collect()
            : $applyScope(Recipe::query()->where('recipes.is_prep', true))
                ->when($prepCategoryIds, fn ($q) => $q->whereIn('recipes.ingredient_category_id', $prepCategoryIds))
                ->leftJoin('ingredient_categories as rc', function ($join) {
                    $join->on('rc.id', '=', 'recipes.ingredient_category_id')
                         ->whereNull('rc.deleted_at');
                })
                ->leftJoin('ingredient_categories as rcp', 'rcp.id', '=', 'rc.parent_id')
                ->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) IS NULL')
                ->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) ASC')
                ->orderByRaw('COALESCE(rcp.name, rc.name) ASC')
                ->orderBy('rc.sort_order')
                ->orderBy('rc.name')
                ->orderBy('recipes.menu_sort_order')
                ->orderBy('recipes.name')
                ->select('recipes.*')
                ->with($eager)
                ->get();

        $recipes = $nonPrep->concat($prep);

        $grouped = $recipes->groupBy(fn ($r) => $r->is_prep
            ? 'Prep — ' . ($r->ingredientCategory?->name ?? 'Uncategorised')
            : ($r->category ?? 'Uncategorised'));
        $logoBase64 = $this->logoToBase64($company);

        // Pre-compute base64 images and QR codes for all recipes
        $recipeImages = [];
        $recipeQrs    = [];
        $recipeStepImages = [];
        foreach ($recipes as $recipe) {
            $recipeImages[$recipe->id] = [
                'dine_in'  => $this->imagesToBase64($recipe->images->whereIn('type', ['dine_in', 'presentation'])->values()),
                'takeaway' => $this->imagesToBase64($recipe->images->where('type', 'takeaway')->values()),
            ];
            $recipeQrs[$recipe->id] = $recipe->video_url ? $this->generateVideoQr($recipe->id, $company->id) : null;
            $recipeStepImages[$recipe->id] = $this->stepImagesToBase64($recipe->steps);
        }

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'SOP';

        $pdf = Pdf::loadView('pdf.sop-all', compact(
            'grouped', 'company', 'logoBase64', 'recipeImages', 'recipeQrs', 'recipeStepImages', 'exportedBy', 'brandName'
        ))->setPaper('a4', 'portrait');

        $fileLabel = $prepOnly
            ? ($prepCategoryLabel ? "Prep-{$prepCategoryLabel}" : 'Prep-Items')
            : ($groupLabel
                ? "All-{$groupLabel}"
                : ($category ?: 'Training-SOPs'));

        return $pdf->download($this->safeFilename("{$brandName}-{$fileLabel}.pdf"));
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
            try {
                $path = Storage::disk('public')->path($step->image_path);
                if (file_exists($path)) {
                    $mime = mime_content_type($path) ?: 'image/jpeg';
                    $data = base64_encode(file_get_contents($path));
                    $result[$step->id] = "data:{$mime};base64,{$data}";
                }
            } catch (\Throwable $e) {
                // skip
            }
        }
        return $result;
    }

    private function imagesToBase64($images): array
    {
        $result = [];
        foreach ($images as $img) {
            try {
                $path = Storage::disk('public')->path($img->file_path);
                if (file_exists($path)) {
                    $data = base64_encode(file_get_contents($path));
                    $result[] = "data:{$img->mime_type};base64,{$data}";
                }
            } catch (\Throwable $e) {
                // Skip images that can't be read
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

    private function logoToBase64(?Company $company): ?string
    {
        if (! $company?->logo) return null;
        try {
            $path = Storage::disk('public')->path($company->logo);
            if (file_exists($path)) {
                $mime = mime_content_type($path);
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
