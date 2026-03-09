<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use Livewire\Component;

class ApiKeys extends Component
{
    public string $google_vision_key = '';
    public string $ai_provider = 'anthropic';
    public string $anthropic_key = '';
    public string $openrouter_key = '';
    public string $openrouter_model = '';

    public function mount(): void
    {
        $this->google_vision_key = AppSetting::get('google_vision_api_key', '');
        $this->ai_provider = AppSetting::get('ai_provider', 'anthropic');
        $this->anthropic_key = AppSetting::get('anthropic_api_key', '');
        $this->openrouter_key = AppSetting::get('openrouter_api_key', '');
        $this->openrouter_model = AppSetting::get('openrouter_model', '');
    }

    public function updatedAiProvider(): void
    {
        AppSetting::set('ai_provider', $this->ai_provider);
        session()->flash('success', 'AI provider updated to ' . ($this->ai_provider === 'openrouter' ? 'OpenRouter' : 'Anthropic Direct') . '.');
    }

    public function saveVision(): void
    {
        $this->validate([
            'google_vision_key' => 'nullable|string|max:500',
        ]);

        AppSetting::set('google_vision_api_key', $this->google_vision_key ?: null);

        session()->flash('success', 'Google Vision API key saved.');
    }

    public function saveAnthropic(): void
    {
        $this->validate([
            'anthropic_key' => 'nullable|string|max:500',
        ]);

        AppSetting::set('anthropic_api_key', $this->anthropic_key ?: null);

        session()->flash('success', 'Anthropic API key saved.');
    }

    public function saveOpenRouter(): void
    {
        $this->validate([
            'openrouter_key'   => 'nullable|string|max:500',
            'openrouter_model' => 'nullable|string|max:255',
        ]);

        AppSetting::set('openrouter_api_key', $this->openrouter_key ?: null);
        AppSetting::set('openrouter_model', $this->openrouter_model ?: null);

        session()->flash('success', 'OpenRouter settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.api-keys')
            ->layout('layouts.app', ['title' => 'API Keys']);
    }
}
