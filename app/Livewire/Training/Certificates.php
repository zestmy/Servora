<?php

namespace App\Livewire\Training;

use App\Models\Company;
use App\Models\TrainingCertificate;
use App\Services\Training\CertificateService;
use App\Traits\RejectsUnpreviewableUploads;
use App\Traits\RequiresActiveCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Issued certificates, and what state they are in.
 *
 * The default filter is "expiring", not "all". A list of every certificate ever
 * issued answers no question anybody has; "whose food-safety certificate lapses
 * this month" is the entire reason a compliance officer opens this screen.
 */
class Certificates extends Component
{
    use RejectsUnpreviewableUploads;
    use RequiresActiveCompany;
    use WithFileUploads;
    use WithPagination;

    /** all | valid | expiring | expired | revoked */
    #[Url(as: 'show', except: 'expiring')]
    public string $filter = 'expiring';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Days ahead the "expiring" filter looks. */
    public int $horizon = 60;

    public function mount(): void
    {
        $this->requireActiveCompany();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ── The signatory ─────────────────────────────────────────────────────
    // Who signs the certificates. Company-wide, edited here because this is
    // the screen where certificates live, and read LIVE at render time like
    // the logo — the PDF is rebuilt from the row on every download.

    public bool $showSignatory = false;

    public string  $signatoryName    = '';
    public string  $signatoryTitle   = '';
    public string  $signatoryCompany = '';
    public ?string $signaturePath    = null;
    public $signatureFile;

    public function openSignatory(): void
    {
        $this->authorize('training.manage');

        $company = Company::findOrFail(Auth::user()->company_id);

        $this->signatoryName    = (string) $company->cert_signatory_name;
        $this->signatoryTitle   = (string) $company->cert_signatory_title;
        $this->signatoryCompany = (string) $company->cert_signatory_company;
        $this->signaturePath    = $company->cert_signature_path;
        $this->signatureFile    = null;

        $this->resetErrorBag();
        $this->showSignatory = true;
    }

    /**
     * Same HEIC trap as the employee photo, same cure: the preview calls
     * temporaryUrl(), which throws for an unconvertible iPhone original and
     * poisons the whole component. See EmployeeForm::updatedPhoto.
     */
    public function updatedSignatureFile(): void
    {
        if (! is_object($this->signatureFile)) {
            return;
        }

        try {
            $this->signatureFile = $this->keepPreviewableUpload($this->signatureFile, 'signatureFile');
        } catch (\Throwable $e) {
            report($e);

            $this->signatureFile = null;
            $this->addError('signatureFile', 'That image could not be read. iPhone tip: Settings → Camera → Formats → Most Compatible, or share it as JPEG.');
        }
    }

    public function saveSignatory(): void
    {
        $this->authorize('training.manage');

        $this->validate([
            'signatoryName'    => ['nullable', 'string', 'max:255'],
            'signatoryTitle'   => ['nullable', 'string', 'max:255'],
            'signatoryCompany' => ['nullable', 'string', 'max:255'],
            'signatureFile'    => ['nullable', 'image', 'max:2048'],
        ]);

        $company = Company::findOrFail(Auth::user()->company_id);

        $data = [
            'cert_signatory_name'    => $this->signatoryName ?: null,
            'cert_signatory_title'   => $this->signatoryTitle ?: null,
            'cert_signatory_company' => $this->signatoryCompany ?: null,
        ];

        if ($this->signatureFile) {
            // The old file goes with the new one — the same update leak the
            // company logo taught us about.
            $previous = $company->cert_signature_path;

            $data['cert_signature_path'] = $this->signatureFile->store('training/signatures', 'public');
            $this->signaturePath = $data['cert_signature_path'];

            if (filled($previous) && $previous !== $data['cert_signature_path']) {
                Storage::disk('public')->delete($previous);
            }
        }

        $company->update($data);
        $this->signatureFile = null;
        $this->showSignatory = false;

        session()->flash('success', 'Certificate signatory updated. Every certificate now prints with it.');
    }

    public function removeSignature(): void
    {
        $this->authorize('training.manage');

        $company = Company::findOrFail(Auth::user()->company_id);

        if ($company->cert_signature_path) {
            Storage::disk('public')->delete($company->cert_signature_path);
            $company->update(['cert_signature_path' => null]);
        }

        $this->signaturePath = null;
        $this->signatureFile = null;
    }

    public function revoke(int $id, CertificateService $service): void
    {
        $this->authorize('training.assign');

        $certificate = TrainingCertificate::findOrFail($id);
        $service->revoke($certificate);

        session()->flash('success', "Certificate {$certificate->serial} revoked.");
    }

    public function reinstate(int $id, CertificateService $service): void
    {
        $this->authorize('training.assign');

        $certificate = TrainingCertificate::findOrFail($id);
        $service->reinstate($certificate);

        session()->flash('success', "Certificate {$certificate->serial} reinstated.");
    }

    public function render()
    {
        $certificates = TrainingCertificate::query()
            // The learner relation is `employee` — `trainee` died with the
            // LmsUser learner. This eager-load only ever ran on a tab that had
            // rows, which is how it survived: every empty tab renders fine, and
            // the first company to actually hold a valid certificate got a 500.
            ->with(['employee:id,name,email', 'course:id,title'])
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('recipient_name', 'like', "%{$this->search}%")
                   ->orWhere('title', 'like', "%{$this->search}%")
                   ->orWhere('serial', 'like', "%{$this->search}%");
            }))
            ->when($this->filter === 'valid', fn ($q) => $q->valid())
            ->when($this->filter === 'expiring', fn ($q) => $q->expiringWithin($this->horizon))
            ->when($this->filter === 'expired', fn ($q) => $q
                ->whereNull('revoked_at')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString()))
            ->when($this->filter === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->latest('issued_at')
            ->paginate(20);

        $counts = [
            'all'      => TrainingCertificate::count(),
            'valid'    => TrainingCertificate::valid()->count(),
            'expiring' => TrainingCertificate::expiringWithin($this->horizon)->count(),
            'expired'  => TrainingCertificate::whereNull('revoked_at')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<', now()->toDateString())->count(),
            'revoked'  => TrainingCertificate::whereNotNull('revoked_at')->count(),
        ];

        return view('livewire.training.certificates', compact('certificates', 'counts'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Certificates']);
    }
}
