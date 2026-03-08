<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use Livewire\Component;

class ApiKeys extends Component
{
    public string $google_vision_key = '';
    public bool   $keyVisible        = false;

    public function mount(): void
    {
        $this->google_vision_key = AppSetting::get('google_vision_api_key', '');
    }

    public function save(): void
    {
        $this->validate([
            'google_vision_key' => 'nullable|string|max:500',
        ]);

        AppSetting::set('google_vision_api_key', $this->google_vision_key ?: null);

        session()->flash('success', 'API key saved.');
    }

    public function render()
    {
        return view('livewire.settings.api-keys')
            ->layout('layouts.app', ['title' => 'API Keys']);
    }
}
