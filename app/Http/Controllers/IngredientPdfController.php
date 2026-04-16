<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class IngredientPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Ingredient::with([
            'baseUom', 'recipeUom', 'ingredientCategory.parent', 'suppliers',
        ]);

        $search   = trim((string) $request->get('search', ''));
        $category = trim((string) $request->get('category', ''));
        $status   = (string) $request->get('status', 'all');
        $supplier = (string) $request->get('supplier', '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if ($category !== '') {
            $cat = IngredientCategory::with('children')->find((int) $category);
            if ($cat) {
                $ids = $cat->children->isNotEmpty()
                    ? $cat->children->pluck('id')->push($cat->id)->toArray()
                    : [$cat->id];
                $query->whereIn('ingredient_category_id', $ids);
            }
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($supplier !== '') {
            if ($supplier === 'none') {
                $query->whereDoesntHave('suppliers');
            } else {
                $query->whereHas('suppliers', fn ($q) => $q->where('suppliers.id', (int) $supplier));
            }
        }

        $ingredients = $query->orderBy('name')->get();

        // Build active filters label
        $filters = [];
        if ($search !== '') $filters[] = 'Search: "' . $search . '"';
        if ($category !== '') {
            $cat = IngredientCategory::find((int) $category);
            if ($cat) $filters[] = 'Category: ' . $cat->name;
        }
        if ($status !== 'all') $filters[] = 'Status: ' . ucfirst($status);
        if ($supplier !== '') {
            if ($supplier === 'none') {
                $filters[] = 'Supplier: None';
            } else {
                $s = Supplier::find((int) $supplier);
                if ($s) $filters[] = 'Supplier: ' . $s->name;
            }
        }

        $company   = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $logoBase64 = $this->companyLogoBase64($company);

        $pdf = Pdf::loadView('pdf.ingredients', compact(
            'ingredients', 'filters', 'brandName', 'logoBase64'
        ))->setPaper('a4', 'landscape');

        $filename = 'Ingredients';
        if ($supplier !== '' && $supplier !== 'none') {
            $s = Supplier::find((int) $supplier);
            if ($s) $filename .= '-' . str_replace(' ', '_', $s->name);
        }

        return $pdf->stream($filename . '-' . now()->format('Y-m-d') . '.pdf');
    }

    private function companyLogoBase64($company): ?string
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
