<?php

namespace App\Livewire\Marketing;

use Livewire\Component;

class Features extends Component
{
    public function render()
    {
        return view('livewire.marketing.features')
            ->layout('layouts.marketing', ['title' => 'Features']);
    }
}
