<?php

namespace App\Livewire\Supplier;

use App\Models\SupplierProduct;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Products extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editId = null;

    public string $sku = '';
    public string $name = '';
    public string $description = '';
    public string $category = '';
    public ?int $uom_id = null;
    public string $pack_size = '1';
    public string $unit_price = '';
    public string $min_order_quantity = '1';
    public string $lead_time_days = '';
    public bool $is_active = true;

    public $csvFile = null;

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $product = SupplierProduct::findOrFail($id);
        $this->editId = $product->id;
        $this->sku = $product->sku;
        $this->name = $product->name;
        $this->description = $product->description ?? '';
        $this->category = $product->category ?? '';
        $this->uom_id = $product->uom_id;
        $this->pack_size = (string) $product->pack_size;
        $this->unit_price = (string) $product->unit_price;
        $this->min_order_quantity = (string) $product->min_order_quantity;
        $this->lead_time_days = (string) ($product->lead_time_days ?? '');
        $this->is_active = $product->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'sku'        => 'required|string|max:50',
            'name'       => 'required|string|max:200',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $supplierId = Auth::guard('supplier')->user()->supplier_id;

        $data = [
            'supplier_id'        => $supplierId,
            'sku'                => $this->sku,
            'name'               => $this->name,
            'description'        => $this->description ?: null,
            'category'           => $this->category ?: null,
            'uom_id'             => $this->uom_id,
            'pack_size'          => $this->pack_size ?: 1,
            'unit_price'         => $this->unit_price,
            'min_order_quantity'  => $this->min_order_quantity ?: 1,
            'lead_time_days'     => $this->lead_time_days ?: null,
            'is_active'          => $this->is_active,
        ];

        if ($this->editId) {
            SupplierProduct::findOrFail($this->editId)->update($data);
        } else {
            SupplierProduct::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $this->editId ? 'Product updated.' : 'Product created.');
    }

    public function delete(int $id): void
    {
        SupplierProduct::findOrFail($id)->delete();
        session()->flash('success', 'Product deleted.');
    }

    public function importCsv(): void
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:2048']);

        $supplierId = Auth::guard('supplier')->user()->supplier_id;
        $file = fopen($this->csvFile->getRealPath(), 'r');
        $header = fgetcsv($file); // skip header
        $count = 0;

        while ($row = fgetcsv($file)) {
            if (count($row) < 3) continue;
            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplierId, 'sku' => trim($row[0])],
                [
                    'name'       => trim($row[1]),
                    'unit_price' => floatval($row[2]),
                    'category'   => $row[3] ?? null,
                    'pack_size'  => isset($row[4]) ? floatval($row[4]) : 1,
                ]
            );
            $count++;
        }
        fclose($file);

        $this->csvFile = null;
        session()->flash('success', "{$count} products imported.");
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->sku = '';
        $this->name = '';
        $this->description = '';
        $this->category = '';
        $this->uom_id = null;
        $this->pack_size = '1';
        $this->unit_price = '';
        $this->min_order_quantity = '1';
        $this->lead_time_days = '';
        $this->is_active = true;
    }

    public function render()
    {
        $supplierId = Auth::guard('supplier')->user()->supplier_id;
        $query = SupplierProduct::where('supplier_id', $supplierId);

        if ($this->search) {
            $query->where(fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('sku', 'like', '%' . $this->search . '%'));
        }

        $products = $query->orderBy('name')->paginate(20);
        $uoms = UnitOfMeasure::orderBy('name')->get();

        return view('livewire.supplier.products', compact('products', 'uoms'))
            ->layout('layouts.supplier', ['title' => 'Products']);
    }
}
