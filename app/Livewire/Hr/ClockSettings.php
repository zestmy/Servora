<?php

namespace App\Livewire\Hr;

use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Outlet;
use App\Services\Geocoding\ReverseGeocoder;
use App\Services\Hr\PresenceHeartbeat;
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

    /** Refuse a punch whose face does not match, rather than flagging it. */
    public bool $require_face_match        = false;
    public bool $mark_attendance           = false;
    public bool $allow_offsite_with_reason = true;
    public bool $resolve_addresses         = false;

    /**
     * Record WHERE a phone was when the Staff Portal was last opened, not
     * just when. See App\Services\Hr\PresenceHeartbeat.
     *
     * Off until somebody turns it on, and turning it off again erases what was
     * collected — the screen says so, because a toggle that keeps yesterday's
     * locations after being switched off is not the toggle it appears to be.
     */
    public bool $location_heartbeat        = false;

    /** Which ways in the company allows at all. Per-outlet mode narrows these. */
    public bool $kiosk_enabled = true;
    public bool $byod_enabled  = true;

    /** Whether the kiosk accepts a PIN when it cannot recognise a face. */
    public bool $kiosk_allow_pin = true;

    /*
     * The kiosk's own face thresholds, kept separate from face_threshold on
     * purpose. That one governs a 1:1 check where the person has already named
     * themselves with a PIN; the kiosk searches the whole outlet, and the
     * mistake it can make is to write a punch onto the wrong person's record.
     */
    public string $kiosk_face_threshold   = '0.45';
    public string $kiosk_face_margin      = '0.08';
    public string $kiosk_cooldown_minutes = '3';

    /**
     * How much noise the kiosk and the staff app make. See ClockSetting::SOUND_MODES.
     *
     * Not a boolean, because the complaint this answers is "the chime is too
     * much in this room" and the honest answer to that is not "then have no
     * feedback at all" — a face scan gives nothing to feel, so somebody with a
     * silent screen stands there leaning in.
     */
    public string $sound_mode = 'full';

    /**
     * The flags that DO send a punch to a manager.
     *
     * Held as the review list rather than the stored skip list because that is
     * what the screen asks — a ticked box means "ask me about this" — and a
     * checkbox bound to the negative of its own label is how a settings screen
     * ends up meaning the opposite of what it says. Inverted once on save.
     */
    public array $reviewFlags = [];

    /** Result of the "test" button on the geocoding block. */
    public ?array $geocodeTest = null;

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
        $this->require_face_match        = (bool) $settings->require_face_match;
        $this->mark_attendance           = (bool) $settings->mark_attendance;
        $this->allow_offsite_with_reason = (bool) $settings->allow_offsite_with_reason;
        $this->resolve_addresses         = (bool) $settings->resolve_addresses;
        $this->location_heartbeat        = (bool) $settings->location_heartbeat;

        $this->kiosk_enabled   = (bool) $settings->kiosk_enabled;
        $this->byod_enabled    = (bool) $settings->byod_enabled;
        $this->kiosk_allow_pin = (bool) $settings->kiosk_allow_pin;

        $this->kiosk_face_threshold = rtrim(rtrim(number_format((float) $settings->kiosk_face_threshold, 3, '.', ''), '0'), '.');
        $this->kiosk_face_margin    = rtrim(rtrim(number_format((float) $settings->kiosk_face_margin, 3, '.', ''), '0'), '.');
        $this->kiosk_cooldown_minutes = (string) $settings->kiosk_cooldown_minutes;

        $this->sound_mode = $settings->soundMode();

        $this->reviewFlags = array_values(array_diff(
            self::policyFlags(),
            $settings->autoApproveFlags(),
        ));

        foreach ($this->outlets() as $outlet) {
            $this->fences[$outlet->id] = [
                'latitude'  => $outlet->latitude !== null ? (string) $outlet->latitude : '',
                'longitude' => $outlet->longitude !== null ? (string) $outlet->longitude : '',
                'radius'    => $outlet->clock_radius_m !== null ? (string) $outlet->clock_radius_m : '150',
                'mode'      => $outlet->punchMode(),
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

            /*
             * Capped at 0.60 rather than the 1:1 line's 0.70. Searching forty
             * faces instead of one means forty chances to land under the bar,
             * and the mistake a loose kiosk threshold makes is not a flag on
             * somebody's punch — it is the punch landing on the wrong person.
             */
            'kiosk_face_threshold'   => ['required', 'numeric', 'min:0.30', 'max:0.60'],
            'kiosk_face_margin'      => ['required', 'numeric', 'min:0.02', 'max:0.30'],
            'kiosk_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:120'],

            'sound_mode'    => ['required', 'in:' . implode(',', array_keys(ClockSetting::SOUND_MODES))],

            'reviewFlags'   => ['array'],
            'reviewFlags.*' => ['string', 'in:' . implode(',', self::policyFlags())],

            'fences.*.latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'fences.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'fences.*.radius'    => ['nullable', 'integer', 'min:20', 'max:5000'],
            'fences.*.mode'      => ['nullable', 'in:kiosk_only,byod_only,both'],
        ];
    }

    protected function messages(): array
    {
        return [
            'fences.*.radius.min' => 'A radius under 20m is tighter than a phone\'s own GPS error.',
            'face_threshold.min'  => 'Below 0.30 almost nobody will match their own enrolment.',
            'face_threshold.max'  => 'Above 0.70 different people start matching each other.',
            'kiosk_face_threshold.max' => 'Above 0.60 the kiosk starts naming the wrong colleague.',
            'kiosk_face_margin.min'    => 'Under 0.02 the kiosk will pick between two similar faces on a coin toss.',
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can('hr.clock.manage'), 403);

        $this->validate();

        $settings = ClockSetting::forCompany(Auth::user()->company_id);

        // Read BEFORE the update, so the transition can be seen at all.
        $heartbeatWasOn = (bool) $settings->location_heartbeat;

        $settings->update([
            'grace_minutes'        => (int) $this->grace_minutes,
            'late_rate_per_minute' => (float) $this->late_rate_per_minute,
            'late_cap_per_shift'   => $this->late_cap_per_shift === '' ? null : (float) $this->late_cap_per_shift,
            'early_window_minutes' => (int) $this->early_window_minutes,
            'max_accuracy_m'       => (int) $this->max_accuracy_m,
            'face_threshold'       => (float) $this->face_threshold,
            'require_gps'          => $this->require_gps,
            'require_face'         => $this->require_face,
            'require_face_match'   => $this->require_face_match,
            'mark_attendance'      => $this->mark_attendance,
            'allow_offsite_with_reason' => $this->allow_offsite_with_reason,
            'resolve_addresses'         => $this->resolve_addresses,
            'location_heartbeat'        => $this->location_heartbeat,
            'kiosk_enabled'             => $this->kiosk_enabled,
            'byod_enabled'              => $this->byod_enabled,
            'kiosk_allow_pin'           => $this->kiosk_allow_pin,
            'kiosk_face_threshold'      => (float) $this->kiosk_face_threshold,
            'kiosk_face_margin'         => (float) $this->kiosk_face_margin,
            'kiosk_cooldown_minutes'    => (int) $this->kiosk_cooldown_minutes,
            /*
             * Stored as the SKIP list, which is the inverse of what the screen
             * collects. Deliberate: a flag added in a later release is absent
             * from every company's skip list and therefore reviewed by default,
             * so a new check starts by asking rather than being silently
             * ignored by everybody who saved this screen before it existed.
             *
             * Always an array, never null. Null means "never configured, use
             * the shipped default" — once a manager has answered this screen,
             * their answer stands even when it happens to match the default.
             */
            'sound_mode' => $this->sound_mode,
            'auto_approve_flags' => array_values(array_diff(
                self::policyFlags(),
                $this->reviewFlags,
            )),
        ]);

        /*
         * Switching location off ERASES what it gathered.
         *
         * Keeping it would leave a company holding staff locations that it has
         * just decided it does not collect — the exact position nobody wants to
         * explain to the person whose row it is. The timestamps stay: "last
         * opened the app 10 minutes ago" is not a location and is not what the
         * toggle governs.
         */
        if ($heartbeatWasOn && ! $this->location_heartbeat) {
            PresenceHeartbeat::forget(Auth::user()->company_id);
        }

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

            $mode = $fence['mode'] ?? null;

            $outlet->update([
                'latitude'       => $hasPair ? (float) $latitude : null,
                'longitude'      => $hasPair ? (float) $longitude : null,
                'clock_radius_m' => $hasPair && trim((string) ($fence['radius'] ?? '')) !== ''
                    ? (int) $fence['radius']
                    : null,
                // Falls back to the outlet's CURRENT mode rather than to a
                // default. A missing key means the control was not on the page
                // — not that somebody chose "own devices only" — and silently
                // resetting an outlet to BYOD would switch its kiosk off.
                'punch_mode'     => is_string($mode) && array_key_exists($mode, Outlet::PUNCH_MODE_LABELS)
                    ? $mode
                    : $outlet->punchMode(),
            ]);
        }

        session()->flash('success', 'Clock-in settings saved.');
    }

    /**
     * Every flag the policy screen offers, flattened.
     *
     * Read off ClockEvent::REVIEW_POLICY_GROUPS rather than FLAG_LABELS so the
     * screen and the saved policy can never cover different sets — a flag with
     * no group would otherwise be un-tickable here and therefore auto-approved
     * for everybody on the next save, which is silent and wrong. A test asserts
     * the groups stay exhaustive.
     *
     * @return array<int, string>
     */
    public static function policyFlags(): array
    {
        return array_merge(...array_column(ClockEvent::REVIEW_POLICY_GROUPS, 'flags'));
    }

    public function outlets()
    {
        return Outlet::whereIn('id', Auth::user()->accessibleOutletIds() ?: [0])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Prove the provider answers, without touching the cache — the point is
     * to test the configuration, not to read back an earlier result.
     */
    public function testGeocoder(): void
    {
        $geocoder = app(ReverseGeocoder::class);

        // KLCC. A fixed, obviously-public point: testing must never require
        // pulling a real employee's coordinates out of the log.
        $this->geocodeTest = $geocoder->test(3.1578, 101.7117) + [
            'provider' => ReverseGeocoder::PROVIDERS[$geocoder->provider()] ?? $geocoder->provider(),
        ];
    }

    public function render()
    {
        $geocoder = app(ReverseGeocoder::class);

        return view('livewire.hr.clock-settings', [
            'outlets'          => $this->outlets(),
            'geocodeProvider'  => ReverseGeocoder::PROVIDERS[$geocoder->provider()] ?? $geocoder->provider(),
            'geocodeReady'     => $geocoder->isConfigured(),
            'soundModes'       => ClockSetting::SOUND_MODES,
            'policyGroups'     => ClockEvent::REVIEW_POLICY_GROUPS,
            'flagLabels'       => ClockEvent::FLAG_LABELS,
        ])->layout('layouts.app', ['title' => 'Clock-In Settings']);
    }
}
