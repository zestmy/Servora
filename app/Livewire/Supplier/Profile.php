<?php

namespace App\Livewire\Supplier;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';
    public string $contact_person = '';
    public string $email = '';
    public string $phone = '';
    public string $whatsapp_number = '';
    public string $notification_preference = 'email';
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $country = 'MY';
    public string $tax_registration_number = '';
    public string $billing_address = '';
    public string $bank_name = '';
    public string $bank_account_number = '';
    public string $payment_terms = '';

    public function mount(): void
    {
        $supplier = Auth::guard('supplier')->user()->supplier;
        $this->name = $supplier->name ?? '';
        $this->contact_person = $supplier->contact_person ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->whatsapp_number = $supplier->whatsapp_number ?? '';
        $this->notification_preference = $supplier->notification_preference ?? 'email';
        $this->address = $supplier->address ?? '';
        $this->city = $supplier->city ?? '';
        $this->state = $supplier->state ?? '';
        $this->country = $supplier->country ?? 'MY';
        $this->tax_registration_number = $supplier->tax_registration_number ?? '';
        $this->billing_address = $supplier->billing_address ?? '';
        $this->bank_name = $supplier->bank_name ?? '';
        $this->bank_account_number = $supplier->bank_account_number ?? '';
        $this->payment_terms = $supplier->payment_terms ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        $supplier = Auth::guard('supplier')->user()->supplier;
        $supplier->update([
            'name'                     => $this->name,
            'contact_person'           => $this->contact_person ?: null,
            'email'                    => $this->email,
            'phone'                    => $this->phone ?: null,
            'whatsapp_number'          => $this->whatsapp_number ?: null,
            'notification_preference'  => $this->notification_preference,
            'address'                  => $this->address ?: null,
            'city'                     => $this->city ?: null,
            'state'                    => $this->state ?: null,
            'country'                  => $this->country ?: 'MY',
            'tax_registration_number'  => $this->tax_registration_number ?: null,
            'billing_address'          => $this->billing_address ?: null,
            'bank_name'                => $this->bank_name ?: null,
            'bank_account_number'      => $this->bank_account_number ?: null,
            'payment_terms'            => $this->payment_terms ?: null,
        ]);

        session()->flash('success', 'Profile updated.');
    }

    public function render()
    {
        return view('livewire.supplier.profile')
            ->layout('layouts.supplier', ['title' => 'Profile']);
    }
}
