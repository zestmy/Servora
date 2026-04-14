<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class CompanyDetails extends Component
{
    use WithFileUploads;

    public string  $name                = '';
    public string  $brand_name          = '';
    public string  $slug                = '';
    public string  $registration_number = '';
    public string  $email               = '';
    public string  $phone               = '';
    public string  $address             = '';
    public string  $billing_address     = '';
    public string  $currency            = 'MYR';
    public string  $timezone            = '';
    public string  $tax_type            = '';
    public string  $tax_percent         = '0';
    public bool    $show_price_on_do_grn = false;
    public bool    $auto_generate_do     = false;
    public bool    $direct_supplier_order = false;
    public string  $po_cc_emails        = '';
    public bool    $ingredients_locked  = false;
    public bool    $recipes_locked      = false;
    public ?string $currentLogo         = null;
    public $logo;

    public function mount(): void
    {
        $company = $this->getCompany();
        if (! $company) return;

        $this->name                = $company->name ?? '';
        $this->brand_name          = $company->brand_name ?? '';
        $this->slug                = $company->slug ?? '';
        $this->registration_number = $company->registration_number ?? '';
        $this->email               = $company->email ?? '';
        $this->phone               = $company->phone ?? '';
        $this->address             = $company->address ?? '';
        $this->billing_address     = $company->billing_address ?? '';
        $this->currency            = $company->currency ?? 'MYR';
        $this->timezone            = $company->timezone ?? '';
        $this->tax_type            = $company->tax_type ?? '';
        $this->tax_percent         = (string) ($company->tax_percent ?? '0');
        $this->show_price_on_do_grn = (bool) $company->show_price_on_do_grn;
        $this->auto_generate_do     = (bool) $company->auto_generate_do;
        $this->direct_supplier_order = (bool) $company->direct_supplier_order;
        $this->po_cc_emails        = $company->po_cc_emails ?? '';
        $this->ingredients_locked  = (bool) $company->ingredients_locked;
        $this->recipes_locked      = (bool) $company->recipes_locked;
        $this->currentLogo         = $company->logo;
    }

    public function save(): void
    {
        $this->validate([
            'name'                => 'required|string|max:100',
            'brand_name'          => 'nullable|string|max:255',
            'slug'                => 'required|string|max:100|alpha_dash|unique:companies,slug,' . ($this->getCompany()?->id ?? 0),
            'registration_number' => 'nullable|string|max:50',
            'email'               => 'nullable|email|max:100',
            'phone'               => 'nullable|string|max:30',
            'address'             => 'nullable|string|max:500',
            'billing_address'     => 'nullable|string|max:500',
            'currency'            => 'required|string|max:5',
            'timezone'            => 'nullable|string|max:64|in:' . implode(',', \DateTimeZone::listIdentifiers()),
            'tax_type'            => 'nullable|string|max:10',
            'tax_percent'         => 'nullable|numeric|min:0|max:100',
            'logo'                => 'nullable|image|max:2048',
        ]);

        $company = $this->getCompany();
        if (! $company) return;

        $data = [
            'name'                => $this->name,
            'brand_name'          => $this->brand_name ?: null,
            'slug'                => strtolower($this->slug),
            'registration_number' => $this->registration_number ?: null,
            'email'               => $this->email ?: null,
            'phone'               => $this->phone ?: null,
            'address'             => $this->address ?: null,
            'billing_address'     => $this->billing_address ?: null,
            'currency'            => $this->currency,
            'timezone'            => $this->timezone ?: null,
            'tax_type'              => $this->tax_type ?: null,
            'tax_percent'           => floatval($this->tax_percent),
            'show_price_on_do_grn'  => $this->show_price_on_do_grn,
            'auto_generate_do'      => $this->auto_generate_do,
            'direct_supplier_order' => $this->direct_supplier_order,
            'po_cc_emails'          => $this->po_cc_emails ?: null,
            'ingredients_locked'    => $this->ingredients_locked,
            'recipes_locked'        => $this->recipes_locked,
        ];

        if ($this->logo) {
            $path = $this->logo->store('company-logos', 'public');
            $data['logo'] = $path;
            $this->currentLogo = $path;
        }

        $company->update($data);
        $this->logo = null;

        session()->flash('success', 'Company details updated.');
    }

    public function render()
    {
        return view('livewire.settings.company-details')
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Company Details']);
    }

    private function getCompany(): ?Company
    {
        $companyId = Auth::user()->company_id;
        return $companyId ? Company::find($companyId) : null;
    }
}
