<?php

namespace App\Livewire\Training;

use App\Models\TrainingCertificate;
use App\Services\Training\CertificateService;
use App\Traits\RequiresActiveCompany;
use Livewire\Attributes\Url;
use Livewire\Component;
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
    use RequiresActiveCompany;
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
            ->with(['trainee:id,name,email', 'course:id,title'])
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
