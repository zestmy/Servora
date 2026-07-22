<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class CalendarEvents extends Component
{
    use WithPagination, WithFileUploads;

    private const PER_PAGE = 15;

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $outletFilter = 'all'; // 'all' | 'company' (outlet_id null) | "<outlet id>"
    public string $yearFilter = '';      // '' = all years, otherwise a 4-digit year

    public bool $showModal = false;
    public ?int $editingId = null;
    public array $editingGroupIds = [];     // all underlying event ids when editing a grouped row

    public string $title = '';
    public string $event_date = '';
    public string $end_date = '';
    public bool $applyAllOutlets = true;    // true = single company-wide event (outlet_id null)
    public array $selectedOutletIds = [];   // specific outlets — one event is stored per outlet
    public string $category = 'other';
    public string $description = '';
    public string $impact = 'neutral';

    // Import
    public bool $showImportModal = false;
    public $importFile = null;
    public array $importPreview = [];
    public array $importErrors = [];
    public int $importCreated = 0;

    // AI public-holiday generation
    public bool $showHolidayModal = false;
    public ?int $holidayOutletId = null; // null = all active outlets
    public int $holidayYear = 0;
    public array $holidayPreview = [];   // rows: outlet_id, outlet_name, date, name, impact, exists, selected
    public array $holidayErrors = [];
    public string $holidayNotice = '';

    public function mount(): void
    {
        $this->holidayYear = (int) now()->year;
    }

    protected function rules(): array
    {
        $rules = [
            'title'      => 'required|string|max:255',
            'event_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:event_date',
            'category'   => 'required|string|in:' . implode(',', array_keys(CalendarEvent::categoryOptions())),
            'description' => 'nullable|string|max:1000',
            'impact'     => 'required|string|in:' . implode(',', array_keys(CalendarEvent::impactOptions())),
        ];

        // When not applying to all outlets, at least one specific outlet is required.
        if (! $this->applyAllOutlets) {
            $rules['selectedOutletIds']   = 'required|array|min:1';
            $rules['selectedOutletIds.*'] = 'integer';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'selectedOutletIds.required' => 'Select at least one outlet, or choose “Apply to all outlets”.',
            'selectedOutletIds.min'      => 'Select at least one outlet, or choose “Apply to all outlets”.',
        ];
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedOutletFilter(): void { $this->resetPage(); }
    public function updatedYearFilter(): void   { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * Edit a grouped row. The ids are the underlying per-outlet events that
     * share the same title/date/category/impact. Shared fields are edited for
     * all of them at once, and the outlet selection can be changed — save()
     * reconciles by adding/removing per-outlet events as needed.
     */
    public function openEditGroup(array $ids): void
    {
        $events = CalendarEvent::with('outlet')
            ->whereIn('id', array_map('intval', $ids))
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $first = $events->first();

        $this->editingGroupIds = $events->pluck('id')->all();
        $this->editingId       = $first->id;
        $this->title           = $first->title;
        $this->event_date      = $first->event_date->format('Y-m-d');
        $this->end_date        = $first->end_date?->format('Y-m-d') ?? '';
        $this->category        = $first->category;
        $this->description     = $first->description ?? '';
        $this->impact          = $first->impact ?? 'neutral';

        $this->applyAllOutlets   = $events->contains(fn ($e) => is_null($e->outlet_id));
        $this->selectedOutletIds = $this->applyAllOutlets
            ? []
            : $events->pluck('outlet_id')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $companyId = Auth::user()->company_id;

        $shared = [
            'title'       => $this->title,
            'event_date'  => $this->event_date,
            'end_date'    => $this->end_date ?: null,
            'category'    => $this->category,
            'description' => $this->description ?: null,
            'impact'      => $this->impact,
        ];

        // Resolve the target outlets: [null] for a single company-wide event,
        // otherwise one event per selected outlet (validated to the company).
        $targets = $this->resolveTargetOutletIds($companyId);
        if ($targets === null) {
            $this->addError('selectedOutletIds', 'Select at least one outlet, or choose “Apply to all outlets”.');
            return;
        }

        if ($this->editingId) {
            try {
                $this->reconcileGroup($targets, $shared, $companyId);
            } catch (\Illuminate\Database\QueryException $e) {
                if (($e->errorInfo[0] ?? null) === '23000') {
                    $this->addError('title', 'An event with this title and date already exists for the selected outlet(s).');
                    return;
                }
                throw $e;
            }
            session()->flash('success', 'Event updated.');
            $this->closeModal();
        } else {
            // Skip targets that already have an identical event so re-adding an
            // existing event shows a friendly message instead of creating a
            // duplicate row (or erroring on databases with a unique index).
            $toCreate = array_values(array_filter(
                $targets,
                fn ($outletId) => ! $this->eventExists($companyId, $outletId)
            ));

            if (empty($toCreate)) {
                $this->addError('title', 'This event already exists in Calendar Events for the selected outlet(s).');
                return;
            }

            $firstEvent = null;
            try {
                foreach ($toCreate as $outletId) {
                    $event = CalendarEvent::create($shared + [
                        'company_id' => $companyId,
                        'outlet_id'  => $outletId,
                        'created_by' => Auth::id(),
                    ]);
                    $firstEvent ??= $event;
                }
            } catch (\Illuminate\Database\QueryException $e) {
                if (($e->errorInfo[0] ?? null) === '23000') {
                    $this->addError('title', 'This event already exists in Calendar Events for the selected outlet(s).');
                    return;
                }
                throw $e;
            }

            $skipped = count($targets) - count($toCreate);
            session()->flash('success', $skipped > 0
                ? "Event created. Skipped {$skipped} outlet(s) that already have this event."
                : 'Event created.');
            $this->closeModal();

            // Clear any active filters so the new event isn't hidden, then jump
            // to the page that contains it — the list is ordered by event_date
            // descending, so an event with an earlier date may not be on page 1.
            $this->search = '';
            $this->categoryFilter = 'all';
            $this->outletFilter = 'all';
            $this->yearFilter = '';
            if ($firstEvent) {
                $this->gotoCreatedEventPage($firstEvent);
            }
        }
    }

    /**
     * Whether an event with the same title and start date already exists for
     * the given outlet target (null = the company-wide event).
     */
    private function eventExists(int $companyId, ?int $outletId): bool
    {
        return CalendarEvent::where('company_id', $companyId)
            ->where('title', $this->title)
            ->whereDate('event_date', $this->event_date)
            ->when(
                $outletId === null,
                fn ($q) => $q->whereNull('outlet_id'),
                fn ($q) => $q->where('outlet_id', $outletId)
            )
            ->exists();
    }

    /**
     * Target outlet ids for the current selection: [null] when applying to all
     * outlets, otherwise the chosen ids restricted to the company's outlets.
     * Returns null when a specific selection ends up empty (invalid).
     */
    private function resolveTargetOutletIds(int $companyId): ?array
    {
        if ($this->applyAllOutlets) {
            return [null];
        }

        $companyOutletIds = Outlet::where('company_id', $companyId)->pluck('id')->all();
        $targets = array_values(array_unique(array_intersect(
            array_map('intval', $this->selectedOutletIds),
            $companyOutletIds
        )));

        return empty($targets) ? null : $targets;
    }

    /**
     * Reconcile the events behind the edited group to match the target outlets:
     * update events whose outlet is still wanted, create missing ones, and
     * delete events whose outlet is no longer selected. Returns one surviving
     * event (for paginator positioning).
     */
    private function reconcileGroup(array $targets, array $shared, int $companyId): ?CalendarEvent
    {
        $existing = CalendarEvent::whereIn('id', $this->editingGroupIds)->get();
        $existingByOutlet = $existing->keyBy(fn ($e) => $e->outlet_id === null ? 'null' : (string) $e->outlet_id);

        $keepKeys = [];
        $firstEvent = null;

        foreach ($targets as $outletId) {
            $key = $outletId === null ? 'null' : (string) $outletId;
            $keepKeys[] = $key;

            if ($existingByOutlet->has($key)) {
                $event = $existingByOutlet->get($key);
                $event->update($shared);
            } else {
                $event = CalendarEvent::create($shared + [
                    'company_id' => $companyId,
                    'outlet_id'  => $outletId,
                    'created_by' => Auth::id(),
                ]);
            }

            $firstEvent ??= $event;
        }

        $toDelete = $existing->reject(
            fn ($e) => in_array($e->outlet_id === null ? 'null' : (string) $e->outlet_id, $keepKeys, true)
        );
        if ($toDelete->isNotEmpty()) {
            CalendarEvent::whereIn('id', $toDelete->pluck('id'))->delete();
        }

        return $firstEvent;
    }

    /**
     * Move the paginator to the page holding the just-created event's grouped
     * row, so a freshly added event is always visible.
     */
    private function gotoCreatedEventPage(CalendarEvent $event): void
    {
        $rows = $this->buildEventRows();
        $index = $rows->search(fn ($row) => in_array($event->id, $row['ids'], true));

        if ($index === false) {
            $index = 0;
        }

        $this->setPage(intdiv($index, self::PER_PAGE) + 1);
    }

    /**
     * Build the grouped event rows for the current filters. Events that share
     * the same title, dates, category and impact are collapsed into a single
     * row that carries the list of outlets they apply to — so a public holiday
     * created for several outlets shows once with outlet tags instead of as
     * one duplicate row per outlet.
     */
    private function buildEventRows(): \Illuminate\Support\Collection
    {
        $query = CalendarEvent::with('outlet');

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }
        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }
        if ($this->outletFilter === 'company') {
            $query->whereNull('outlet_id');
        } elseif ($this->outletFilter !== 'all') {
            $query->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->yearFilter !== '') {
            $query->whereYear('event_date', $this->yearFilter);
        }

        $events = $query->orderByDesc('event_date')->orderByDesc('id')->get();

        return $events
            ->groupBy(fn ($e) => implode('|', [
                $e->title,
                $e->event_date->format('Y-m-d'),
                $e->end_date?->format('Y-m-d') ?? '',
                $e->category,
                $e->impact ?? 'neutral',
            ]))
            ->map(function ($group) {
                $first = $group->first();
                $outletNames = $group->filter(fn ($e) => $e->outlet_id)
                    ->map(fn ($e) => $e->outlet?->name)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'ids'            => $group->pluck('id')->all(),
                    'title'          => $first->title,
                    'event_date'     => $first->event_date,
                    'end_date'       => $first->end_date,
                    'category'       => $first->category,
                    'category_label' => $first->categoryLabel(),
                    'impact'         => $first->impact ?? 'neutral',
                    'description'    => $first->description,
                    'all_outlets'    => $group->contains(fn ($e) => is_null($e->outlet_id)),
                    'outlet_names'   => $outletNames,
                    'count'          => $group->count(),
                ];
            })
            ->values();
    }

    public function delete(int $id): void
    {
        CalendarEvent::findOrFail($id)->delete();
        session()->flash('success', 'Event deleted.');
    }

    /** Delete every event behind a grouped row (one per outlet). */
    public function deleteGroup(array $ids): void
    {
        $count = CalendarEvent::whereIn('id', array_map('intval', $ids))->delete();
        session()->flash('success', $count > 1 ? "{$count} events deleted." : 'Event deleted.');
    }

    public function openImport(): void
    {
        $this->importFile = null;
        $this->importPreview = [];
        $this->importErrors = [];
        $this->importCreated = 0;
        $this->showImportModal = true;
    }

    /**
     * Stream an .xlsx template with the expected columns and a few sample rows,
     * so users can fill it in Excel and upload it back via the importer.
     */
    public function downloadTemplate()
    {
        $headers = ['title', 'event_date', 'end_date', 'category', 'impact', 'description'];
        $samples = [
            ['Chinese New Year', '2026-01-29', '2026-01-31', 'holiday', 'positive', 'Public holiday'],
            ['Ramadan Starts', '2026-02-18', '', 'external', 'negative', 'Fasting month begins'],
            ['Lunch Promo', '2026-03-01', '2026-03-15', 'promotion', 'positive', '20% off lunch set'],
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'cal_tpl');

        $writer = new XlsxWriter();
        $writer->openToFile($tmp);
        $writer->addRow(SpoutRow::fromValues($headers));
        foreach ($samples as $sample) {
            $writer->addRow(SpoutRow::fromValues($sample));
        }
        $writer->close();

        return response()->download($tmp, 'calendar_events_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function updatedImportFile(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $this->parseImportFile();
    }

    private function parseImportFile(): void
    {
        $this->importPreview = [];
        $this->importErrors = [];

        $ext = strtolower($this->importFile->getClientOriginalExtension());
        $path = $this->importFile->getRealPath();

        try {
            [$header, $dataRows] = in_array($ext, ['xlsx', 'xls'])
                ? $this->readSpreadsheetRows($path)
                : $this->readCsvRows($path);
        } catch (\Throwable $e) {
            $this->importErrors[] = 'Could not read the file.';
            return;
        }

        if (! $header) {
            $this->importErrors[] = 'File is empty.';
            return;
        }

        // Normalise header names
        $header = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        $requiredCols = ['title', 'event_date'];
        $missing = array_diff($requiredCols, $header);
        if ($missing) {
            $this->importErrors[] = 'Missing required columns: ' . implode(', ', $missing) . '. Required: title, event_date. Optional: end_date, category, impact, description.';
            return;
        }

        $validCategories = array_keys(CalendarEvent::categoryOptions());
        $validImpacts = array_keys(CalendarEvent::impactOptions());
        $row = 1;

        foreach ($dataRows as $data) {
            $row++;
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $mapped = array_combine($header, array_slice($data, 0, count($header)));

            $title = trim($mapped['title'] ?? '');
            $eventDate = trim($mapped['event_date'] ?? '');
            $endDate = trim($mapped['end_date'] ?? '');
            $category = strtolower(trim($mapped['category'] ?? 'other'));
            $impact = strtolower(trim($mapped['impact'] ?? 'neutral'));
            $description = trim($mapped['description'] ?? '');

            // Validate
            $rowErrors = [];
            if (empty($title)) {
                $rowErrors[] = 'title is required';
            }
            if (empty($eventDate)) {
                $rowErrors[] = 'event_date is required';
            } else {
                try {
                    $eventDate = Carbon::parse($eventDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    $rowErrors[] = 'invalid event_date format';
                }
            }
            if ($endDate) {
                try {
                    $endDate = Carbon::parse($endDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    $rowErrors[] = 'invalid end_date format';
                    $endDate = '';
                }
            }
            if (! in_array($category, $validCategories)) {
                $category = 'other';
            }
            if (! in_array($impact, $validImpacts)) {
                $impact = 'neutral';
            }

            $entry = [
                'row'        => $row,
                'title'      => $title,
                'event_date' => $eventDate,
                'end_date'   => $endDate,
                'category'   => $category,
                'impact'     => $impact,
                'description' => $description,
                'errors'     => $rowErrors,
                'valid'      => empty($rowErrors),
            ];

            $this->importPreview[] = $entry;
        }

        if (empty($this->importPreview)) {
            $this->importErrors[] = 'No data rows found in the file.';
        }
    }

    /**
     * Read a CSV file into [headerRow, dataRows] with raw (un-normalised) values.
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Unable to open file.');
        }

        $header = fgetcsv($handle) ?: null;
        $rows = [];
        if ($header) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
        }
        fclose($handle);

        return [$header, $rows];
    }

    /**
     * Read the first sheet of an .xlsx/.xls file into [headerRow, dataRows].
     */
    private function readSpreadsheetRows(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);

        $header = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $r) {
                $cells = array_map(fn ($c) => $this->spoutCellToString($c->getValue()), $r->getCells());

                if ($header === null) {
                    $header = $cells;
                    continue;
                }

                // Skip fully-empty rows
                if (count(array_filter($cells, fn ($v) => $v !== '')) === 0) {
                    continue;
                }

                $rows[] = $cells;
            }
            break; // first sheet only
        }

        $reader->close();

        return [$header, $rows];
    }

    /**
     * Normalise a spreadsheet cell value to a trimmed string. Date cells come
     * back as DateTime objects, so format them to Y-m-d to match the template.
     */
    private function spoutCellToString($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    public function confirmImport(): void
    {
        $companyId = Auth::user()->company_id;
        $userId = Auth::id();
        $created = 0;

        foreach ($this->importPreview as $entry) {
            if (! $entry['valid']) {
                continue;
            }

            CalendarEvent::create([
                'company_id'  => $companyId,
                'title'       => $entry['title'],
                'event_date'  => $entry['event_date'],
                'end_date'    => $entry['end_date'] ?: null,
                'category'    => $entry['category'],
                'impact'      => $entry['impact'],
                'description' => $entry['description'] ?: null,
                'created_by'  => $userId,
            ]);
            $created++;
        }

        $this->showImportModal = false;
        $this->importFile = null;
        $this->importPreview = [];
        $this->importErrors = [];

        session()->flash('success', "{$created} event(s) imported successfully.");
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importPreview = [];
        $this->importErrors = [];
    }

    // ── AI public-holiday generation ────────────────────────────────────────

    public function openHolidays(): void
    {
        $this->holidayOutletId = null;
        $this->holidayYear = (int) now()->year;
        $this->holidayPreview = [];
        $this->holidayErrors = [];
        $this->holidayNotice = '';
        $this->showHolidayModal = true;
    }

    /**
     * Ask the LLM for public holidays for each target outlet's location and
     * build a preview, skipping events that already exist for that outlet/date.
     */
    public function generateHolidays(\App\Services\PublicHolidayService $service): void
    {
        $this->holidayPreview = [];
        $this->holidayErrors = [];
        $this->holidayNotice = '';

        $companyId = Auth::user()->company_id;

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->when($this->holidayOutletId, fn ($q) => $q->where('id', $this->holidayOutletId))
            ->orderBy('name')
            ->get();

        if ($outlets->isEmpty()) {
            $this->holidayErrors[] = 'No active branches found.';
            return;
        }

        $located = $outlets->filter(fn ($o) => filled($o->country));
        $missing = $outlets->filter(fn ($o) => blank($o->country));

        if ($located->isEmpty()) {
            $this->holidayErrors[] = 'No branch has a Country set. Add Country (and State) to your branches in Settings > Branches first.';
            return;
        }

        if ($missing->isNotEmpty()) {
            $this->holidayNotice = 'Skipped — no country set: ' . $missing->pluck('name')->implode(', ') . '.';
        }

        // One AI call per distinct country+state, then fan the result out to
        // every outlet in that location group.
        $groups = $located->groupBy(fn ($o) => mb_strtolower(trim($o->country)) . '|' . mb_strtolower(trim((string) $o->state)));

        set_time_limit(240);
        $preview = [];

        try {
            foreach ($groups as $group) {
                $sample = $group->first();
                $holidays = $service->generate($sample->country, $sample->state ?: null, $this->holidayYear);

                foreach ($group as $outlet) {
                    foreach ($holidays as $h) {
                        $exists = CalendarEvent::where('company_id', $companyId)
                            ->where('category', 'holiday')
                            ->where('outlet_id', $outlet->id)
                            ->whereDate('event_date', $h['date'])
                            ->where('title', $h['name'])
                            ->exists();

                        $preview[] = [
                            'outlet_id'   => $outlet->id,
                            'outlet_name' => $outlet->name,
                            'date'        => $h['date'],
                            'name'        => $h['name'],
                            'impact'      => $h['impact'],
                            'exists'      => $exists,
                            'selected'    => ! $exists,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->holidayErrors[] = $e->getMessage();
            return;
        }

        if (empty($preview)) {
            $this->holidayErrors[] = 'No holidays were returned. Please try again.';
            return;
        }

        usort($preview, fn ($a, $b) => [$a['outlet_name'], $a['date']] <=> [$b['outlet_name'], $b['date']]);
        $this->holidayPreview = $preview;
    }

    public function confirmHolidays(): void
    {
        $companyId = Auth::user()->company_id;
        $userId = Auth::id();
        $created = 0;

        foreach ($this->holidayPreview as $entry) {
            if (empty($entry['selected']) || ! empty($entry['exists'])) {
                continue;
            }

            // Re-check to avoid a duplicate if it was created since the preview.
            $dup = CalendarEvent::where('company_id', $companyId)
                ->where('category', 'holiday')
                ->where('outlet_id', $entry['outlet_id'])
                ->whereDate('event_date', $entry['date'])
                ->where('title', $entry['name'])
                ->exists();
            if ($dup) {
                continue;
            }

            CalendarEvent::create([
                'company_id'  => $companyId,
                'outlet_id'   => $entry['outlet_id'],
                'title'       => $entry['name'],
                'event_date'  => $entry['date'],
                'end_date'    => null,
                'category'    => 'holiday',
                'impact'      => in_array($entry['impact'], ['positive', 'negative', 'neutral'], true) ? $entry['impact'] : 'neutral',
                'description' => 'Public holiday (AI-generated)',
                'created_by'  => $userId,
            ]);
            $created++;
        }

        $this->closeHolidayModal();
        session()->flash('success', "{$created} public holiday event(s) added.");
    }

    public function closeHolidayModal(): void
    {
        $this->showHolidayModal = false;
        $this->holidayPreview = [];
        $this->holidayErrors = [];
        $this->holidayNotice = '';
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $rows = $this->buildEventRows();

        $page = $this->getPage();
        $events = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url()],
        );

        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        $categoryOptions = CalendarEvent::categoryOptions();
        $impactOptions = CalendarEvent::impactOptions();

        // Distinct years present in the data (newest first) for the year filter.
        $years = CalendarEvent::query()
            ->selectRaw('DISTINCT YEAR(event_date) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->all();

        return view('livewire.settings.calendar-events', compact('events', 'outlets', 'categoryOptions', 'impactOptions', 'years'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Calendar Events']);
    }

    private function resetForm(): void
    {
        $this->editingId         = null;
        $this->editingGroupIds   = [];
        $this->title       = '';
        $this->event_date  = '';
        $this->end_date    = '';
        $this->applyAllOutlets   = true;
        $this->selectedOutletIds = [];
        $this->category    = 'other';
        $this->description = '';
        $this->impact      = 'neutral';
        $this->resetValidation();
    }
}
