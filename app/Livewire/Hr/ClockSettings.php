<?php

namespace App\Livewire\Hr;

use App\Models\ClockSetting;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Clock-in policy for the company, and the geofence for each outlet.
 *
 * Two things on one screen because they are one decision in practice: there
 * is no point setting a lateness charge before the outlets have coordinates,
 * and a manager who has just set the coordinates is exactly the person about
 * to ask what happens when somebody is late.
 */
class ClockSettings extends Component
{
    public string $grace_minutes        = '5';
    public string $late_rate_per_minute = '0';
    public string $late_cap_per_shift   = '';
    public string $early_window_minutes = '120';
    public string $max_accuracy_m       = '100';
    public string $face_threshold       = '0.5';

    public bool $require_gps               = true;
    public bool $require_face              = true;
    public bool $mark_attendance           = false;
    public bool $allow_offsite_with_reason = true;

    /** outlet_id => ['latitude' => .., 'longitude' => .., 'radius' => ..] */
    public array $fences = [];

    public function mount(): void
    {
        $settings = ClockSetting::forCompany(Auth::user()->company_id);

        $this->grace_minutes        = (string) $settings->grace_minutes;
        $this->late_rate_per_minute = rtrim(rtrim(number_format((float) $settings->late_rate_per_minute, 2, '.', ''), '0'), '.') ?: '0';
        $this->late_cap_per_shift   = $settings->late_cap_per_shift !== null
            ? rtrim(rtrim(number_format((float) $settings->late_cap_per_shift, 2, '.', ''), '0'), '.')
            : '';
        $this->early_window_minutes = (string) $settings->early_window_minutes;
        $this->max_accuracy_m       = (string) $settings->max_accuracy_m;
        $this->face_threshold       = rtrim(rtrim(number_format((float) $settings->face_threshold, 3, '.', ''), '0'), '.');

        $this->require_gps               = (bool) $settings->require_gps;
        $this->require_face              = (bool) $settings->require_face;
        $this->mark_attendance           = (bool) $settings->mark_attendance;
        $this->allow_offsite_with_reason = (bool) $settings->allow_offsite_with_reason;

        foreach ($this->outlets() as $outlet) {
            $this->fences[$outlet->id] = [
                'latitude'  => $outlet->latitude !== null ? (string) $outlet->latitude : '',
                'longitude' => $outlet->longitude !== null ? (string) $outlet->longitude : '',
                'radius'    => $outlet->clock_radius_m !== null ? (string) $outlet->clock_radius_m : '150',
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'grace_minutes'        => ['required', 'integer', 'min:0', 'max:240'],
            'late_rate_per_minute' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'late_cap_per_shift'   => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'early_window_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_accuracy_m'       => ['required', 'integer', 'min:5', 'max:5000'],
            // The usable band for this model. Below 0.3 nobody matches; above
            // 0.7 two different people routinely do, which is worse than no
            // check at all because it looks like one.
            'face_threshold'       => ['required', 'numeric', 'min:0.30', 'max:0.70'],

            'fences.*.latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'fences.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'fences.*.radius'    => ['nullable', 'integer', 'min:20', 'max:5000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'fences.*.radius.min' => 'A radius under 20m is tighter than a phone\'s own GPS error.',
            'face_threshold.min'  => 'Below 0.30 almost nobody will match their own enrolment.',
            'face_threshold.max'  => 'Above 0.70 different people start matching each other.',
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can('hr.clock.manage'), 403);

        $this->validate();

        $settings = ClockSetting::forCompany(Auth::user()->company_id);

        $settings->update([
            'grace_minutes'        => (int) $this->grace_minutes,
            'late_rate_per_minute' => (float) $this->late_rate_per_minute,
            'late_cap_per_shift'   => $this->late_cap_per_shift === '' ? null : (float) $this->late_cap_per_shift,
            'early_window_minutes' => (int) $this->early_window_minutes,
            'max_accuracy_m'       => (int) $this->max_accuracy_m,
            'face_threshold'       => (float) $this->face_threshold,
            'require_gps'          => $this->require_gps,
            'require_face'         => $this->require_face,
            'mark_attendance'      => $this->mark_attendance,
            'allow_offsite_with_reason' => $this->allow_offsite_with_reason,
        ]);

        foreach ($this->outlets() as $outlet) {
            $fence = $this->fences[$outlet->id] ?? null;

            if (! $fence) {
                continue;
            }

            $latitude  = trim((string) ($fence['latitude'] ?? ''));
            $longitude = trim((string) ($fence['longitude'] ?? ''));

            // Both or neither. A half-set coordinate would silently place the
            // outlet on the equator, and a geofence measured from there fails
            // every punch in the country.
            $hasPair = $latitude !== '' && $longitude !== '';

            $outlet->update([
                'latitude'       => $hasPair ? (float) $latitude : null,
                'longitude'      => $hasPair ? (float) $longitude : null,
                'clock_radius_m' => $hasPair && trim((string) ($fence['radius'] ?? '')) !== ''
                    ? (int) $fence['radius']
                    : null,
            ]);
        }

        session()->flash('success', 'Clock-in settings saved.');
    }

    public function outlets()
    {
        return Outlet::whereIn('id', Auth::user()->accessibleOutletIds() ?: [0])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.hr.clock-settings', [
            'outlets' => $this->outlets(),
        ])->layout('layouts.app', ['title' => 'Clock-In Settings']);
    }
}
