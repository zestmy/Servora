<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SopPdfController extends Controller
{
    public function single(int $id)
    {
        $user = Auth::guard('lms')->check()
            ? Auth::guard('lms')->user()
            : Auth::user();

        $company = Company::find($user->company_id);

        $recipe = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->with(['steps', 'images', 'lines.ingredient', 'lines.uom', 'yieldUom'])
            ->findOrFail($id);

        $dineInImages   = $recipe->images->where('type', 'dine_in')->values();
        $takeawayImages = $recipe->images->where('type', 'takeaway')->values();

        // Convert images to base64 for DomPDF
        $dineInBase64   = $this->imagesToBase64($dineInImages);
        $takeawayBase64 = $this->imagesToBase64($takeawayImages);
        $logoBase64     = $this->logoToBase64($company);

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'Company';
        $videoQr    = $this->generateQr($recipe->video_url);

        $pdf = Pdf::loadView('pdf.sop-single', compact(
            'recipe', 'company', 'dineInBase64', 'takeawayBase64', 'logoBase64', 'exportedBy', 'brandName', 'videoQr'
        ))->setPaper('a4', 'portrait');

        return $pdf->download("SOP-{$recipe->code}-{$recipe->name}.pdf");
    }

    public function all()
    {
        $user = Auth::guard('lms')->check()
            ? Auth::guard('lms')->user()
            : Auth::user();

        $company = Company::find($user->company_id);

        $recipes = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('is_prep', false)
            ->has('steps')
            ->with(['steps', 'images', 'lines.ingredient', 'lines.uom', 'yieldUom'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $grouped = $recipes->groupBy(fn ($r) => $r->category ?? 'Uncategorised');
        $logoBase64 = $this->logoToBase64($company);

        // Pre-compute base64 images and QR codes for all recipes
        $recipeImages = [];
        $recipeQrs    = [];
        foreach ($recipes as $recipe) {
            $recipeImages[$recipe->id] = [
                'dine_in'  => $this->imagesToBase64($recipe->images->where('type', 'dine_in')->values()),
                'takeaway' => $this->imagesToBase64($recipe->images->where('type', 'takeaway')->values()),
            ];
            $recipeQrs[$recipe->id] = $this->generateQr($recipe->video_url);
        }

        $exportedBy = $user->name;
        $brandName  = $company->brand_name ?? $company->name ?? 'SOP';

        $pdf = Pdf::loadView('pdf.sop-all', compact(
            'grouped', 'company', 'logoBase64', 'recipeImages', 'recipeQrs', 'exportedBy', 'brandName'
        ))->setPaper('a4', 'portrait');
        return $pdf->download("{$brandName}-Training-SOPs.pdf");
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

    private function generateQr(?string $url): ?string
    {
        if (! $url) return null;
        try {
            $options = new QROptions([
                'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
                'scale'        => 5,
                'quietzoneSize' => 1,
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
