<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\SalesCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Departments extends Component
{
    /** The dropdown value for "cost this department against total sales". */
    public const TOTAL_SALES = 'total';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name              = '';
    /** A sales category id, self::TOTAL_SALES, or '' for non-revenue. */
    public string $sales_category_id = '';
    public string $sort_order        = '0';
    public bool   $is_active         = true;

    protected function rules(): array
    {
        return [
            'name'              => 'required|string|max:100',
            'sales_category_id' => ['nullable', function ($attribute, $value, $fail) {
                if ($value === '' || $value === null || $value === self::TOTAL_SALES) {
                    return;
                }
                if (! SalesCategory::whereKey((int) $value)->exists()) {
                    $fail('Choose a sales category from the list.');
                }
            }],
            'sort_order'        => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $dept = Department::findOrFail($id);

        $this->editingId         = $dept->id;
        $this->name              = $dept->name;
        $this->sales_category_id = $dept->costs_against_total_sales
            ? self::TOTAL_SALES
            : (string) ($dept->sales_category_id ?? '');
        $this->sort_order        = (string) $dept->sort_order;
        $this->is_active         = $dept->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $total = $this->sales_category_id === self::TOTAL_SALES;

        $data = [
            'name'                      => $this->name,
            'sales_category_id'         => $total || $this->sales_category_id === '' ? null : (int) $this->sales_category_id,
            'costs_against_total_sales' => $total,
            'sort_order'        => (int) $this->sort_order,
            'is_active'         => $this->is_active,
        ];

        if ($this->editingId) {
            Department::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Department updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            Department::create($data);
            session()->flash('success', 'Department created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $dept = Department::findOrFail($id);

        $usedCount = PurchaseOrder::where('department_id', $dept->id)->count();
        if ($usedCount > 0) {
            session()->flash('error', "Cannot delete \"{$dept->name}\" — it is used by {$usedCount} purchase " . ($usedCount === 1 ? 'order' : 'orders') . '.');
            return;
        }

        $dept->delete();
        session()->flash('success', 'Department deleted.');
    }

    public function toggleActive(int $id): void
    {
        $dept = Department::findOrFail($id);
        $dept->update(['is_active' => ! $dept->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $departments     = Department::with('salesCategory')->ordered()->get();
        $salesCategories = SalesCategory::where('is_active', true)->orderBy('name')->get();

        $usage = PurchaseOrder::selectRaw('department_id, count(*) as total')
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        return view('livewire.settings.departments', compact('departments', 'salesCategories', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Departments']);
    }

    private function resetForm(): void
    {
        $this->editingId         = null;
        $this->name              = '';
        $this->sales_category_id = '';
        $this->sort_order        = '0';
        $this->is_active         = true;
        $this->resetValidation();
    }
}
