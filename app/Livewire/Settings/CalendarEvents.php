<?php

namespace App\Livewire\Settings;

use App\Models\CalendarEvent;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CalendarEvents extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $categoryFilter = 'all';

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

    public function updatedImportFile(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $this->parseImportFile();
    }

    private function parseImportFile(): void
    {
        $this->importPreview = [];
        $this->importErrors = [];

        $path = $this->importFile->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            $this->importErrors[] = 'Could not read the file.';
            return;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $this->importErrors[] = 'File is empty.';
            return;
        }

        // Normalise header names
        $header = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        $requiredCols = ['title', 'event_date'];
        $missing = array_diff($requiredCols, $header);
        if ($missing) {
            fclose($handle);
            $this->importErrors[] = 'Missing required columns: ' . implode(', ', $missing) . '. Required: title, event_date. Optional: end_date, category, impact, description.';
            return;
        }

        $validCategories = array_keys(CalendarEvent::categoryOptions());
        $validImpacts = array_keys(CalendarEvent::impactOptions());
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
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

        fclose($handle);

        if (empty($this->importPreview)) {
            $this->importErrors[] = 'No data rows found in the file.';
        }
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
