<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\LeaveSetting;
use App\Models\Outlet;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The public holidays this company recognises, and what a day worked on one
 * is worth.
 *
 * Two settings carry the module. Whether a holiday is REPLACEABLE — false
 * means the company pays for the day at the statutory rate instead of giving
 * it back, which is a legitimate choice and has to be recorded rather than
 * left as an empty balance nobody can explain. And the CLAIM WINDOW, which
 * bounds when the replacement day may be taken; set once for the company here,
 * overridable on the year-end holidays that need a longer rope.
 *
 * Imports from the sales calendar rather than reading it. Those rows exist to
 * explain revenue to AI Analytics and get reworded and deleted with that in
 * mind; an entitlement that vanished because someone tidied the marketing
 * calendar would be impossible to diagnose.
 */
class PublicHolidays extends Component
{
    public string $year = '';

    // Holiday form
    public bool  $showForm  = false;
    public ?int  $editingId = null;

    public string $f_date        = '';
    public string $f_name        = '';
    public bool   $f_replaceable = true;
    public bool   $f_all_outlets = true;
    public array  $f_outlet_ids  = [];
    public string $f_expiry_mode = '';     // '' = follow the company default
    public string $f_expiry_days = '';
    public string $f_notes       = '';
    public bool   $f_is_active   = true;

    // Company default window
    public bool   $showDefaults  = false;
    public string $d_expiry_mode = LeaveSetting::EXPIRY_DAYS;
    public string $d_expiry_days = '90';

    // Import from the sales calendar
    public bool  $showImport    = false;
    public array $importRows    = [];
    public string $importNotice = '';

    public function mount(): void
    {
        $this->year = (string) now()->year;

        $settings = LeaveSetting::forCompany(Auth::user()->company_id);
        $this->d_expiry_mode = $settings->rph_expiry_mode;
        $this->d_expiry_days = (string) $settings->rph_expiry_days;
    }

    private function settings(): LeaveSetting
    {
        return LeaveSetting::forCompany(Auth::user()->company_id);
    }

    /**
     * Drop the credit service's per-request holiday cache.
     *
     * It is a container singleton so fifty employees on the leave screen share
     * one query. Every mutation on this screen has to invalidate it, or a
     * balance rendered later in the SAME request would be the one from before
     * the save.
     */
    private function holidaysChanged(): void
    {
        app(\App\Services\Hr\ReplacementHolidays::class)->forget();
    }

    // ── Company default claim window ─────────────────────────────────────

    public function saveDefaults(): void
    {
        $this->validate([
            'd_expiry_mode' => 'required|in:' . implode(',', array_keys(LeaveSetting::EXPIRY_MODES)),
            'd_expiry_days' => 'required_if:d_expiry_mode,days|nullable|integer|min:1|max:730',
        ], [], ['d_expiry_days' => 'number of days']);

        $this->settings()->update([
            'rph_expiry_mode' => $this->d_expiry_mode,
            // Kept at whatever it was when the mode is not "days", so
            // switching to year-end and back does not silently lose the
            // number somebody chose.
            'rph_expiry_days' => $this->d_expiry_mode === LeaveSetting::EXPIRY_DAYS
                ? (int) $this->d_expiry_days
                : (int) ($this->d_expiry_days ?: 90),
        ]);

        $this->holidaysChanged();
        $this->showDefaults = false;
        session()->flash('success', 'Default claim window saved.');
    }

    // ── Holidays ─────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->resetForm();
        $this->f_date = $this->year . '-' . now()->format('m-d');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $holiday = PublicHoliday::with('outlets:id')->findOrFail($id);

        $this->editingId     = $holiday->id;
        $this->f_date        = $holiday->date->toDateString();
        $this->f_name        = $holiday->name;
        $this->f_replaceable = (bool) $holiday->is_replaceable;
        $this->f_all_outlets = $holiday->outlets->isEmpty();
        $this->f_outlet_ids  = $holiday->outlets->pluck('id')->map(fn ($i) => (string) $i)->all();
        $this->f_expiry_mode = (string) $holiday->expiry_mode;
        $this->f_expiry_days = $holiday->expiry_days ? (string) $holiday->expiry_days : '';
        $this->f_notes       = (string) $holiday->notes;
        $this->f_is_active   = (bool) $holiday->is_active;

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'f_date'        => 'required|date',
            'f_name'        => 'required|string|max:255',
            'f_expiry_mode' => 'nullable|in:' . implode(',', array_keys(LeaveSetting::EXPIRY_MODES)),
            'f_expiry_days' => 'required_if:f_expiry_mode,days|nullable|integer|min:1|max:730',
            'f_notes'       => 'nullable|string|max:255',
        ], [], [
            'f_date' => 'date', 'f_name' => 'name', 'f_expiry_days' => 'number of days',
        ]);

        if (! $this->f_all_outlets && empty($this->f_outlet_ids)) {
            $this->addError('f_outlet_ids', 'Pick at least one branch, or apply it to all of them.');
            return;
        }

        // A duplicate would issue two credits for one day off. The unique
        // index would catch it, but as a 500 rather than a sentence.
        $clash = PublicHoliday::where('date', $this->f_date)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->f_name))])
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($clash) {
            $this->addError('f_name', 'That holiday is already in the register for this date.');
            return;
        }

        $data = [
            'company_id'     => $companyId,
            'date'           => $this->f_date,
            'name'           => trim($this->f_name),
            'is_replaceable' => $this->f_replaceable,
            'expiry_mode'    => $this->f_expiry_mode ?: null,
            // Only meaningful on the "days" override — storing a number
            // against year-end would read as policy that is not there.
            'expiry_days'    => $this->f_expiry_mode === LeaveSetting::EXPIRY_DAYS && $this->f_expiry_days !== ''
                ? (int) $this->f_expiry_days
                : null,
            'notes'          => trim($this->f_notes) ?: null,
            'is_active'      => $this->f_is_active,
        ];

        if ($this->editingId) {
            $holiday = PublicHoliday::findOrFail($this->editingId);
            $holiday->update($data);
        } else {
            $data['created_by'] = Auth::id();
            $holiday = PublicHoliday::create($data);
        }

        // Empty pivot means every outlet — see the migration.
        $holiday->outlets()->sync($this->f_all_outlets
            ? []
            : $this->accessibleOutlets()->whereIn('id', array_map('intval', $this->f_outlet_ids))->pluck('id')->all());

        $this->holidaysChanged();
        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', 'Public holiday saved.');
    }

    public function toggleActive(int $id): void
    {
        $holiday = PublicHoliday::findOrFail($id);
        $holiday->update(['is_active' => ! $holiday->is_active]);
        $this->holidaysChanged();
    }

    /**
     * Deleted only while nothing has been taken against it.
     *
     * Once a day off has been booked as a replacement for this holiday, the
     * row is the only record of what that day was for — switching it off keeps
     * the history readable and stops it issuing any further credits.
     */
    public function delete(int $id): void
    {
        $holiday = PublicHoliday::withCount('requests')->findOrFail($id);

        if ($holiday->requests_count > 0) {
            session()->flash('error', 'Leave has already been taken against this holiday — switch it off instead of deleting, or those days lose what they were for.');
            return;
        }

        $holiday->outlets()->detach();
        $holiday->delete();
        $this->holidaysChanged();
        session()->flash('success', 'Public holiday removed.');
    }

    // ── Import from the sales calendar ───────────────────────────────────

    /**
     * Offer the "holiday" calendar events for the year that are not already in
     * the register.
     *
     * A copy, not a link. Settings > Calendar Events already generates a
     * year's holidays from the LLM, and retyping sixteen dates by hand is the
     * step that gets skipped — but those rows belong to sales forecasting and
     * cannot be allowed to govern an entitlement.
     */
    public function openImport(): void
    {
        $year = (int) $this->year;

        $existing = PublicHoliday::forYear($year)->get()
            ->map(fn ($h) => $h->date->toDateString() . '|' . mb_strtolower($h->name))
            ->flip();

        $rows = [];

        foreach (CalendarEvent::where('category', 'holiday')
            ->whereBetween('event_date', ["{$year}-01-01", "{$year}-12-31"])
            ->orderBy('event_date')->get() as $event) {

            $key = $event->event_date->toDateString() . '|' . mb_strtolower($event->title);

            // The calendar stores one row per outlet, so a company-wide
            // holiday arrives here several times over.
            if (isset($existing[$key]) || isset($rows[$key])) {
                continue;
            }

            $rows[$key] = [
                'date'     => $event->event_date->toDateString(),
                'name'     => $event->title,
                'selected' => true,
            ];
        }

        $this->importRows   = array_values($rows);
        $this->importNotice = empty($rows)
            ? 'Nothing to import — every holiday on the ' . $year . ' calendar is already in the register.'
            : '';
        $this->showImport = true;
    }

    public function runImport(): void
    {
        $companyId = Auth::user()->company_id;
        $created   = 0;

        foreach ($this->importRows as $row) {
            if (empty($row['selected'])) {
                continue;
            }

            // Imported as replaceable and company-wide, which is the common
            // case; the exceptions are quicker to correct afterwards than to
            // tick sixteen times during the import.
            PublicHoliday::firstOrCreate(
                ['company_id' => $companyId, 'date' => $row['date'], 'name' => $row['name']],
                ['is_replaceable' => true, 'is_active' => true, 'created_by' => Auth::id()]
            );

            $created++;
        }

        $this->holidaysChanged();
        $this->showImport = false;
        $this->importRows = [];

        session()->flash('success', $created
            ? "{$created} holiday(s) imported. Check which of them are replaceable before staff start applying."
            : 'Nothing was selected.');
    }

    // ── Plumbing ─────────────────────────────────────────────────────────

    private function accessibleOutlets()
    {
        return Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'f_name', 'f_notes', 'f_expiry_days', 'f_outlet_ids']);
        $this->f_replaceable = true;
        $this->f_all_outlets = true;
        $this->f_expiry_mode = '';
        $this->f_is_active   = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $year     = (int) ($this->year ?: now()->year);
        $settings = $this->settings();

        $holidays = PublicHoliday::with('outlets:id,name')
            ->withCount('requests')
            ->forYear($year)
            ->orderBy('date')
            ->get();

        return view('livewire.settings.public-holidays', [
            'holidays' => $holidays,
            'settings' => $settings,
            'outlets'  => $this->accessibleOutlets(),
            'years'    => range((int) now()->year - 2, (int) now()->year + 1),
            'replaceableCount' => $holidays->where('is_replaceable', true)->where('is_active', true)->count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Public Holidays']);
    }
}
