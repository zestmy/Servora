<?php

namespace App\Livewire\Components;

use Livewire\Component;

class UpgradePrompt extends Component
{
    public string $metric = '';
    public int $current = 0;
    public ?int $limit = null;
    public string $message = '';

    public function mount(string $metric = '', int $current = 0, ?int $limit = null, string $message = ''): void
    {
        $this->metric = $metric;
        $this->current = $current;
        $this->limit = $limit;
        $this->message = $message ?: "You've reached your plan limit of {$limit} " . str_replace('_', ' ', $metric) . '.';
    }

    public function render()
    {
        return view('livewire.components.upgrade-prompt');
    }
}
