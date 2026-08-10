<?php

namespace App\Livewire\Marketing;

use Livewire\Component;

/**
 * The free tools, in one place.
 *
 * The nav said "Free Tools" while pointing at a single calculator, which was
 * fine for exactly as long as there was one. This is the page that label was
 * always describing.
 */
class ToolsIndex extends Component
{
    public function render()
    {
        return view('livewire.marketing.tools-index', [
            'tools' => [
                [
                    'route' => 'tools.recipe-cost',
                    'name'  => 'Recipe Cost Calculator',
                    'blurb' => 'Build a recipe from real ingredient prices and see what it costs '
                        . 'to make, per portion, and what it should sell for.',
                    'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                ],
                [
                    'route' => 'tools.food-cost',
                    'name'  => 'Food Cost Percentage Calculator',
                    'blurb' => 'What a dish costs you against what it sells for — and what to '
                        . 'charge, or what to spend, to hit the percentage you want.',
                    'icon'  => 'M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
                ],
            ],
        ])->layout('layouts.marketing', ['title' => 'Free Tools for F&B Operators']);
    }
}
