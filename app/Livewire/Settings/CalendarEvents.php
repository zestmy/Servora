<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\Outlet;
use Carbon\Carbon;
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

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $outletFilter = 'all'; // 'all' | 'company' (outlet_id null) | "<outlet id>"

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $title = '';
    public string $event_date = '';
    public string $end_date = '';
    public ?int $outlet_id = null;
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
        return [
            'title'      => 'required|string|max:255',
            'event_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:event_date',
            'outlet_id'  => 'nullable|exists:outlets,id',
            'category'   => 'required|string|in:' . implode(',', array_keys(CalendarEvent::categoryOptions())),
            'description' => 'nullable|string|max:1000',
            'impact'     => 'required|string|in:' . implode(',', array_keys(CalendarEvent::impactOptions())),
        ];
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedOutletFilter(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $event = CalendarEvent::findOrFail($id);

        $this->editingId   = $event->id;
        $this->title       = $event->title;
        $this->event_date  = $event->event_date->format('Y-m-d');
        $this->end_date    = $event->end_date?->format('Y-m-d') ?? '';
        $this->outlet_id   = $event->outlet_id;
        $this->category    = $event->category;
        $this->description = $event->description ?? '';
        $this->impact      = $event->impact ?? 'neutral';

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'       => $this->title,
            'event_date'  => $this->event_date,
            'end_date'    => $this->end_date ?: null,
            'outlet_id'   => $this->outlet_id ?: null,
            'category'    => $this->category,
            'description' => $this->description ?: null,
            'impact'      => $this->impact,
        ];

        if ($this->editingId) {
            CalendarEvent::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Event updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();
            CalendarEvent::create($data);
            session()->flash('success', 'Event created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        CalendarEvent::findOrFail($id)->delete();
        session()->flash('success', 'Event deleted.');
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

        $events = $query->orderByDesc('event_date')->paginate(15);
        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        $categoryOptions = CalendarEvent::categoryOptions();
        $impactOptions = CalendarEvent::impactOptions();

        return view('livewire.settings.calendar-events', compact('events', 'outlets', 'categoryOptions', 'impactOptions'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Calendar Events']);
    }

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->title       = '';
        $this->event_date  = '';
        $this->end_date    = '';
        $this->outlet_id   = null;
        $this->category    = 'other';
        $this->description = '';
        $this->impact      = 'neutral';
        $this->resetValidation();
    }
}
