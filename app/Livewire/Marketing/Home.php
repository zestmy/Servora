<?php

namespace App\Livewire\Marketing;

use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        return view('livewire.marketing.home')
            ->layout('layouts.marketing', ['title' => 'F&B Management Platform']);
    }
}
