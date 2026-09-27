<?php

namespace App\Livewire\Hr;

use App\Models\Section;
use App\Services\Hr\RosterPdfException;
use App\Services\Hr\RosterPdfImport;
use App\Services\Hr\RosterPdfParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload the Excel roster as a PDF, check what was read, import it.
 *
 * Two steps, and the second one is the point: the sheet is read in full and
 * laid out as the week it will become, every name already matched where the
 * match is certain, so the manager's job is to glance, fix the one or two
 * rows that are highlighted, and press Import. Nothing is written until then.
 */
class RosterPdfImportModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?int $outletId = null;
    public ?int $sectionId = null;

    /** The uploaded PDF (Livewire temporary upload). */
    public $pdf = null;
    public string $fileName = '';
    public string $error = '';

    /** What RosterPdfParser read — null until a file has been read. */
    public ?array $parsed = null;

    /** Sheet row index => employee id, '' to skip the row. */
    public array $assign = [];

    /** Sheet row index => how the suggestion was made (remembered | name | ambiguous | none). */
    public array $how = [];

    /** Station labels, as on the sheet, that will be created on import. */
    public array $createStations = [];

    #[On('open-roster-pdf-import')]
    public function show(?int $outletId = null, ?int $sectionId = null): void
    {
        $this->resetState();
        $this->outletId = $outletId;
        $this->sectionId = $sectionId;
        $this->open = true;
    }

    public function close(): void
    {
        $this->resetState();
        $this->open = false;
    }

    /** Back to the drop zone, keeping the modal open. */
    public function startOver(): void
    {
        $this->resetState();
    }

    private function resetState(): void
    {
        $this->pdf = null;
        $this->fileName = '';
        $this->error = '';
        $this->parsed = null;
        $this->assign = [];
        $this->how = [];
        $this->createStations = [];
        $this->resetValidation();
    }

    /** Read the file the moment it lands — there is no separate "Upload" click. */
    public function updatedPdf(): void
    {
        $this->error = '';

        $this->validate(
            ['pdf' => 'required|file|mimes:pdf|max:10240'],
            ['pdf.mimes' => 'That is not a PDF. In Excel use File → Save as PDF, then upload the PDF.']
        );

        $this->fileName = $this->pdf->getClientOriginalName();

        try {
            $this->parsed = app(RosterPdfParser::class)->parseFile($this->pdf->getRealPath());
        } catch (RosterPdfException $e) {
            $this->error = $e->getMessage();
            $this->parsed = null;
            return;
        } catch (\Throwable $e) {
            Log::warning('Roster PDF import could not read file', ['file' => $this->fileName, 'error' => $e->getMessage()]);
            $this->error = 'Something in this PDF could not be read. Try saving it from Excel again, or send it to support.';
            $this->parsed = null;
            return;
        }

        // An FOH sheet uploaded while no section is picked: the file name or
        // the title usually says which section it is.
        if (! $this->sectionId) {
            $haystack = mb_strtoupper($this->fileName . ' ' . $this->parsed['title']);
            $hits = Section::active()->get()->filter(
                fn ($s) => preg_match('/\b' . preg_quote(mb_strtoupper($s->name), '/') . '\b/u', $haystack)
            );
            if ($hits->count() === 1) {
                $this->sectionId = $hits->first()->id;
            }
        }

        $this->suggest();
    }

    public function updatedSectionId(): void
    {
        if ($this->parsed) {
            $this->suggest();
        }
    }

    private function suggest(): void
    {
        if (! $this->outletId || ! $this->parsed) {
            return;
        }

        $suggestions = app(RosterPdfImport::class)->suggest($this->parsed['rows'], $this->outletId, $this->sectionId);

        $this->assign = [];
        $this->how = [];
        foreach ($suggestions as $i => $s) {
            $this->assign[$i] = $s['employee_id'] ? (string) $s['employee_id'] : '';
            $this->how[$i] = $s['how'];
        }

        // Every unknown station is offered for creation, ticked: on these
        // sheets the line under a shift is nearly always a station.
        $review = app(RosterPdfImport::class)->review($this->parsed, $this->outletId, $this->sectionId, $this->assign);
        $this->createStations = array_values($review['unknown_stations']);
    }

    /** A manual pick is a decision; stop calling it a guess. */
    public function updatedAssign($value, $key): void
    {
        $this->how[$key] = $value === '' ? 'skipped' : 'picked';
    }

    public function import(): void
    {
        if (! $this->parsed || ! $this->outletId) {
            return;
        }

        $service = app(RosterPdfImport::class);
        $review = $service->review($this->parsed, $this->outletId, $this->sectionId, $this->assign);

        if ($review['blocked']) {
            $this->error = $review['blocked'];
            return;
        }

        $user = Auth::user();
        $permission = $review['roster'] ? 'roster.edit' : 'roster.create';
        if (! $user->can($permission)) {
            $this->error = $review['roster']
                ? 'You do not have permission to edit rosters.'
                : 'You do not have permission to create rosters.';
            return;
        }

        if (! $user->accessibleOutlets()->where('outlets.id', $this->outletId)->exists()) {
            $this->error = 'You do not have access to this outlet.';
            return;
        }

        try {
            $result = $service->apply($this->parsed, $this->outletId, (int) $this->sectionId, $this->assign, $this->createStations, $user);
        } catch (RosterPdfException $e) {
            $this->error = $e->getMessage();
            return;
        }

        $message = sprintf(
            '%s roster for %s: %d staff, %d day(s) imported from %s.',
            $result['created'] ? 'Created the' : 'Updated the',
            Carbon::parse($this->parsed['week_start'])->format('j M') . ' – ' . Carbon::parse($this->parsed['week_end'])->format('j M Y'),
            $result['staff'],
            $result['entries'],
            $this->fileName ?: 'the PDF'
        );
        if ($result['stations']) {
            $message .= " {$result['stations']} new station(s) added.";
        }

        $this->dispatch('roster-pdf-imported',
            weekStart: $this->parsed['week_start'],
            sectionId: (int) $this->sectionId,
            message: $message,
        );

        $this->close();
    }

    public function render()
    {
        $review = null;
        $employees = collect();
        $sections = Section::active()->ordered()->get();

        if ($this->parsed && $this->outletId) {
            $service = app(RosterPdfImport::class);
            $employees = $service->employees($this->outletId);
            $review = $service->review($this->parsed, $this->outletId, $this->sectionId, $this->assign);
        }

        return view('livewire.hr.roster-pdf-import-modal', [
            'review'    => $review,
            'employees' => $employees,
            'sections'  => $sections,
            'leaveShort' => \App\Models\RosterEntry::LEAVE_SHORT,
        ]);
    }
}
