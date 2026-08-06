<?php

namespace App\Livewire\Hr;

use App\Models\ClockDevice;
use App\Models\ClockSetting;
use App\Models\Outlet;
use App\Services\Hr\ClockDeviceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Clock kiosks: which tablets are registered, and whether they are alive.
 *
 * Two jobs on one screen, and the second is the one that will get used daily.
 * Adding a kiosk happens once per outlet; noticing that the kitchen tablet
 * has been offline since Tuesday is a thing somebody needs to see every
 * morning — because while it is down, every phone punch at that outlet stops
 * being flagged and the control quietly switches itself off.
 */
class ClockDevices extends Component
{
    public string $newName     = '';
    public string $newOutletId = '';

    /**
     * The code just issued, and for which device.
     *
     * Held in component state rather than flashed to the session because it
     * has to survive a re-render — a manager reads it out, the tablet is in
     * another room, and a message that vanishes on the next Livewire round
     * trip is a code they have to reissue.
     */
    #[Locked]
    public ?int $codeDeviceId = null;

    #[Locked]
    public string $issuedCode = '';

    #[Locked]
    public ?int $renamingId = null;

    public string $renameValue = '';

    public function mount(): void
    {
        $outlets = $this->outlets();

        if ($outlets->count() === 1) {
            $this->newOutletId = (string) $outlets->first()->id;
        }
    }

    /** Outlets this user may actually put a kiosk in. */
    public function outlets()
    {
        return Outlet::whereIn('id', Auth::user()->accessibleOutletIds() ?: [0])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create the device row and issue its first pairing code.
     *
     * The row exists before the tablet does, which is the whole point of the
     * two-step: the outlet a kiosk vouches for is chosen here, by somebody who
     * knows where it is going, and never by the device itself.
     */
    public function add(ClockDeviceService $devices): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'newName'     => ['required', 'string', 'max:120'],
            'newOutletId' => ['required', 'integer'],
        ], [], [
            'newName'     => 'name',
            'newOutletId' => 'outlet',
        ]);

        $outletId = (int) $validated['newOutletId'];

        abort_unless(in_array($outletId, Auth::user()->accessibleOutletIds(), true), 403);

        $device = ClockDevice::create([
            'company_id' => Auth::user()->company_id,
            'outlet_id'  => $outletId,
            'name'       => trim($validated['newName']),
            'created_by' => Auth::id(),
        ]);

        $this->issuedCode   = $devices->issuePairingCode($device);
        $this->codeDeviceId = $device->id;

        $this->newName = '';

        $this->warnIfOutletStillByod($outletId);
    }

    /** A fresh code — for a lost one, or one that expired on the walk over. */
    public function reissue(int $id, ClockDeviceService $devices): void
    {
        $this->authorizeManage();

        $device = $this->find($id);

        if (! $device || $device->isRevoked()) {
            return;
        }

        $this->issuedCode   = $devices->issuePairingCode($device);
        $this->codeDeviceId = $device->id;
    }

    public function dismissCode(): void
    {
        $this->issuedCode   = '';
        $this->codeDeviceId = null;
    }

    public function startRename(int $id): void
    {
        $device = $this->find($id);

        if (! $device) {
            return;
        }

        $this->renamingId  = $device->id;
        $this->renameValue = $device->name;
    }

    public function cancelRename(): void
    {
        $this->renamingId  = null;
        $this->renameValue = '';
    }

    public function saveRename(): void
    {
        $this->authorizeManage();

        $device = $this->renamingId ? $this->find($this->renamingId) : null;
        $name   = trim($this->renameValue);

        if (! $device || $name === '') {
            return;
        }

        $device->update(['name' => mb_substr($name, 0, 120)]);

        $this->cancelRename();
        session()->flash('success', 'Kiosk renamed.');
    }

    /**
     * Take a tablet out of service.
     *
     * The first thing somebody does when one goes missing, so it is one press
     * and it takes effect on the device's very next request. Punches already
     * recorded keep pointing at it — a stolen kiosk is exactly when you want
     * to ask what it recorded, and deleting the row would take that away.
     */
    public function revoke(int $id, ClockDeviceService $devices): void
    {
        $this->authorizeManage();

        $device = $this->find($id);

        if (! $device || $device->isRevoked()) {
            return;
        }

        $devices->revoke($device, Auth::user());

        if ($this->codeDeviceId === $device->id) {
            $this->dismissCode();
        }

        session()->flash('success', $device->name . ' revoked. It will stop working immediately.');
    }

    /**
     * Nudge, rather than change anything.
     *
     * Registering a kiosk does not switch the outlet over to it — the mode is
     * a policy decision about how people clock in, and a screen for adding a
     * tablet is not where that should happen by side effect. But somebody who
     * has just added one almost certainly means to, and finding out weeks
     * later that the kiosk never became the expected route is worse than a
     * sentence here.
     */
    private function warnIfOutletStillByod(int $outletId): void
    {
        $outlet = Outlet::find($outletId);

        if ($outlet && $outlet->punchMode() === Outlet::PUNCH_BYOD_ONLY) {
            session()->flash('warning', sprintf(
                '%s is still set to "own devices only", so phone punches there will not be flagged. '
                . 'Change it under Clock-in settings when you are ready.',
                $outlet->name
            ));
        }
    }

    private function find(int $id): ?ClockDevice
    {
        return ClockDevice::whereIn('outlet_id', Auth::user()->accessibleOutletIds() ?: [0])
            ->find($id);
    }

    private function authorizeManage(): void
    {
        abort_unless(Auth::user()->can('hr.clock.manage'), 403);
    }

    /**
     * The address somebody types into the tablet.
     *
     * Built with the company slug by hand, because this screen runs on the
     * MAIN domain and the kiosk lives on the company subdomain. The
     * companySlug URL default is set by ResolveCompanyFromSubdomain, which by
     * definition has not run here — so a bare route() call throws
     * UrlGenerationException rather than producing the wrong link.
     */
    private function pairUrl(): string
    {
        $slug = Auth::user()->company?->slug;

        try {
            return config('app.domain') && $slug
                ? route('clock.kiosk.pair', ['companySlug' => $slug])
                : route('clock.kiosk.pair');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function render()
    {
        $devices = ClockDevice::with(['outlet', 'creator', 'revoker'])
            ->whereIn('outlet_id', Auth::user()->accessibleOutletIds() ?: [0])
            // Live ones first, then the revoked history underneath.
            ->orderByRaw('revoked_at is not null')
            ->orderBy('name')
            ->get();

        return view('livewire.hr.clock-devices', [
            'devices'  => $devices,
            'outlets'  => $this->outlets(),
            'settings' => ClockSetting::forCompany(Auth::user()->company_id),
            'pairUrl'  => $this->pairUrl(),
        ])->layout('layouts.app', ['title' => 'Clock Kiosks']);
    }
}
