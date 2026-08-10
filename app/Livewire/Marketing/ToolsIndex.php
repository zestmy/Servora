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
                [
                    'route' => 'tools.menu-matrix',
                    'name'  => 'Menu Engineering Matrix',
                    'blurb' => 'Put every dish against how often it sells and how much it leaves '
                        . 'behind, and get four groups with four different instructions.',
                    'icon'  => 'M4 4v16h16M8 16V9m4 7V5m4 11v-4',
                ],
                [
                    'route' => 'tools.salary',
                    'name'  => 'Salary Calculator',
                    'blurb' => 'Monthly take-home after EPF, SOCSO, EIS and PCB — and what the '
                        . 'same person actually costs to employ.',
                    'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'route' => 'tools.ea-form',
                    'name'  => 'Borang EA Generator',
                    'blurb' => 'The statement of remuneration you owe every employee by the end '
                        . 'of February — from annual totals, or month by month off the payslips.',
                    'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ],
            ],
        ])->layout('layouts.marketing', ['title' => 'Free Tools for F&B Operators']);
    }
}
