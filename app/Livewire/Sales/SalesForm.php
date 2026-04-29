<?php

namespace App\Livewire\Sales;

use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesRecordAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class SalesForm extends Component
{
    use WithFileUploads;
    public ?int    $recordId         = null;
    public string  $sale_date        = '';
    public string  $meal_period      = 'all_day';
    public         $pax              = 1;
    public         $transactions     = null;
    public string  $reference_number = '';
    public string  $notes            = '';

    // Z-report financial breakdown (all nullable)
    public $gross_revenue   = null;
    public $discount_amount = null;
    public $tax_amount      = null;
    public $service_charges = null;
    public $rounding_amount = null;

    public array $newAttachments    = [];
    public array $existingAttachments = [];

    /**
     * One entry per active sales category.
     * Shape: [sales_category_id, ingredient_category_id, category_name, category_color, revenue (string)]
     */
    public array $lines = [];

    protected function rules(): array
    {
        return [
            'sale_date'       => 'required|date',
            'meal_period'     => 'required|in:all_day,breakfast,lunch,tea_time,dinner,supper',
            'pax'             => 'required|integer|min:1',
            'transactions'    => 'nullable|integer|min:0',
            'reference_number'=> 'nullable|string|max:100',
            'notes'           => 'nullable|string',
            'lines.*.revenue'    => 'required|numeric|min:0',
            'newAttachments.*'   => 'file|mimes:jpg,jpeg,png,gif,webp,pdf|max:5120',
            'gross_revenue'   => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount'      => 'nullable|numeric|min:0',
            'service_charges' => 'nullable|numeric|min:0',
            'rounding_amount' => 'nullable|numeric',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->sale_date = now()->toDateString();

        $categories = SalesCategory::active()->ordered()->get();

        // Build a default lines array — one row per sales category
        $this->lines = $categories->map(fn ($cat) => [
            'sales_category_id'      => $cat->id,
            'ingredient_category_id' => null,
            'category_name'          => $cat->name,
            'category_color'         => $cat->color ?? '#6b7280',
            'revenue'                => '0',
        ])->toArray();

        if (! $id) return;

        $record = SalesRecord::with(['lines', 'attachments'])->findOrFail($id);

        $this->recordId         = $record->id;
        $this->sale_date        = $record->sale_date->toDateString();
        $this->meal_period      = $record->meal_period ?? 'all_day';
        $this->pax              = $record->pax ?? 1;
        $this->transactions     = $record->transactions;
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';

        // Z-report financial breakdown
        $this->gross_revenue   = $record->gross_revenue   !== null ? (string) floatval($record->gross_revenue)   : null;
        $this->discount_amount = $record->discount_amount !== null ? (string) floatval($record->discount_amount) : null;
        $this->tax_amount      = $record->tax_amount      !== null ? (string) floatval($record->tax_amount)      : null;
        $this->service_charges = $record->service_charges !== null ? (string) floatval($record->service_charges) : null;
        $this->rounding_amount = $record->rounding_amount !== null ? (string) floatval($record->rounding_amount) : null;

        // Load existing attachments for display
        $this->existingAttachments = $record->attachments->map(fn ($a) => [
            'id'        => $a->id,
            'file_name' => $a->file_name,
            'url'       => $a->url(),
            'is_image'  => $a->isImage(),
            'size'      => $a->humanSize(),
        ])->toArray();

        // Map saved line revenues back — try sales_category_id first, fall back to item_name
        $savedBySalesCategory = $record->lines->whereNotNull('sales_category_id')->keyBy('sales_category_id');
        $savedByItemName      = $record->lines->keyBy(fn ($l) => strtolower(trim((string) $l->item_name)));

        foreach ($this->lines as $idx => $line) {
            $saved = $savedBySalesCategory->get($line['sales_category_id'])
                  ?? $savedByItemName->get(strtolower(trim($line['category_name'])));

            if ($saved) {
                $this->lines[$idx]['revenue'] = (string) floatval($saved->total_revenue);
            }
        }

        // Last-resort fallback: if zero revenue mapped but the record has a non-zero total
        // (e.g. Z-report session records where lines have no category ID or name match),
        // put the record total into the first line so the user isn't staring at RM0.00.
        $mapped = collect($this->lines)->sum(fn ($l) => floatval($l['revenue']));
        if ($mapped == 0 && $record->total_revenue > 0 && !empty($this->lines)) {
            $this->lines[0]['revenue'] = (string) round((float) $record->total_revenue, 4);
        }
    }

    public function save(): void
    {
        $this->validate();

        $totalRevenue = collect($this->lines)->sum(fn ($l) => floatval($l['revenue']));
        $outletId     = Outlet::where('company_id', Auth::user()->company_id)->value('id');

        $data = [
            'sale_date'        => $this->sale_date,
            'meal_period'      => $this->meal_period,
            'pax'              => (int) $this->pax,
            'transactions'     => $this->transactions !== null && $this->transactions !== '' ? (int) $this->transactions : null,
            'reference_number' => $this->reference_number ?: null,
            'notes'            => $this->notes ?: null,
            'total_revenue'    => round($totalRevenue, 4),
            'total_cost'       => 0,
            'gross_revenue'    => $this->gross_revenue   !== null && $this->gross_revenue   !== '' ? round((float) $this->gross_revenue, 4)   : null,
            'discount_amount'  => $this->discount_amount !== null && $this->discount_amount !== '' ? round((float) $this->discount_amount, 4) : null,
            'tax_amount'       => $this->tax_amount      !== null && $this->tax_amount      !== '' ? round((float) $this->tax_amount, 4)      : null,
            'service_charges'  => $this->service_charges !== null && $this->service_charges !== '' ? round((float) $this->service_charges, 4) : null,
            'rounding_amount'  => $this->rounding_amount !== null && $this->rounding_amount !== '' ? round((float) $this->rounding_amount, 4) : null,
        ];

        if ($this->recordId) {
            $record = SalesRecord::findOrFail($this->recordId);
            $record->update($data);
            session()->flash('success', 'Sales record updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $outletId;
            $data['created_by'] = Auth::id();
            $record = SalesRecord::create($data);
            session()->flash('success', 'Sales record created.');
        }

        // Sync lines — only save lines where revenue > 0
        $record->lines()->delete();
        foreach ($this->lines as $line) {
            $revenue = floatval($line['revenue']);
            if ($revenue <= 0) continue;

            $record->lines()->create([
                'sales_category_id'      => $line['sales_category_id'],
                'ingredient_category_id' => $line['ingredient_category_id'],
                'item_name'              => $line['category_name'],
                'quantity'               => 1,
                'unit_price'             => $revenue,
                'unit_cost'              => 0,
                'total_revenue'          => round($revenue, 4),
                'total_cost'             => 0,
            ]);
        }

        // Save new attachments
        foreach ($this->newAttachments as $file) {
            $path = $file->store('sales-attachments', 'public');

            $record->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        $this->redirectRoute('sales.index');
    }

    public function removeExistingAttachment(int $id): void
    {
        $attachment = SalesRecordAttachment::find($id);
        if ($attachment) {
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
            $this->existingAttachments = array_values(
                array_filter($this->existingAttachments, fn ($a) => $a['id'] !== $id)
            );
        }
    }

    public function removeNewAttachment(int $index): void
    {
        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }

    public function render()
    {
        $grandTotal = collect($this->lines)->sum(fn ($l) => floatval($l['revenue']));
        $pax        = (int) $this->pax;
        $avgCheck   = ($pax > 0 && $grandTotal > 0) ? round($grandTotal / $pax, 2) : null;

        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        $pageTitle = $this->recordId ? 'Edit Sales Record' : 'New Sales Entry';

        return view('livewire.sales.sales-form', compact(
            'grandTotal', 'avgCheck', 'mealPeriodOptions'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }
}
