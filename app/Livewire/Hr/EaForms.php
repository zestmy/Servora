<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Services\Payroll\EaForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Borang EA for a year: who gets one, and what it will say.
 *
 * Shown before it is issued rather than after, because the figures come from
 * committed payroll and the things this system CANNOT know — benefits in kind,
 * living accommodation, CP38, TP1 relief — have to be added by hand. Seeing
 * the list first is what makes that possible.
 */
class EaForms extends Component
{
    public string $year        = '';
    public string $outletFilter = '';

    public function mount(): void
    {
        $years = $this->service()->availableYears(Auth::user()->company_id);

        // Default to the most recent year with committed payroll, falling back
        // to LAST year — EA forms are issued in February for the year before.
        $this->year = (string) ($years[0] ?? now()->subYear()->year);
    }

    private function service(): EaForm
    {
        return app(EaForm::class);
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function render()
    {
        $user = Auth::user();

        $outletId = $this->outletFilter !== '' ? (int) $this->outletFilter : null;

        // Re-checked rather than trusted from the select: a year's pay for an
        // outlet the user cannot see is exactly the data the scope exists for.
        if ($outletId !== null && ! in_array($outletId, $this->accessibleOutletIds(), true)) {
            $outletId = null;
            $this->outletFilter = '';
        }

        $forms = $this->service()->forYear(
            $user->company_id,
            $this->accessibleOutletIds(),
            (int) $this->year,
            $outletId,
        );

        $outlets = Outlet::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereIn('id', $this->accessibleOutletIds())
            ->orderBy('name')
            ->get();

        return view('livewire.hr.ea-forms', [
            'forms'    => $forms,
            'years'    => $this->service()->availableYears($user->company_id),
            'outlets'  => $outlets,
            'employer' => $this->service()->employer($user->company_id),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'EA Forms']);
    }
}
