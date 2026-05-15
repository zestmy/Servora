<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\RosterSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RosterSettings extends Component
{
    public ?int $outletId = null;
    public string $normal_hours = '8.00';
    public int $rest_duration = 60;
    public string $week_start_day = 'monday';

    public function mount(): void
    {
        $this->outletId = Auth::user()?->activeOutletId();
        $this->loadSettings();
    }

    public function updatedOutletId(): void
    {
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        if (!$this->outletId) {
            return;
        }

        $settings = RosterSetting::where('outlet_id', $this->outletId)->first();

        if ($settings) {
            $this->normal_hours = (string) $settings->normal_hours;
            $this->rest_duration = $settings->rest_duration;
            $this->week_start_day = $settings->week_start_day ?? 'monday';
        } else {
            // Default values
            $this->normal_hours = '8.00';
            $this->rest_duration = 60;
            $this->week_start_day = 'monday';
        }
    }

    public function save(): void
    {
        $this->validate([
            'outletId' => 'required|exists:outlets,id',
            'normal_hours' => 'required|numeric|min:1|max:24',
            'rest_duration' => 'required|integer|min:0|max:480',
            'week_start_day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        RosterSetting::updateOrCreate(
            ['outlet_id' => $this->outletId],
            [
                'normal_hours' => (float) $this->normal_hours,
                'rest_duration' => $this->rest_duration,
                'week_start_day' => $this->week_start_day,
            ]
        );

        session()->flash('success', 'Roster settings saved.');
    }

    protected function accessibleOutlets()
    {
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->orderBy('name')->get();
        }
        return $user->outlets()->orderBy('name')->get();
    }

    public function render()
    {
        $outlets = $this->accessibleOutlets();

        return view('livewire.hr.roster-settings', compact('outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roster Settings']);
    }
}
