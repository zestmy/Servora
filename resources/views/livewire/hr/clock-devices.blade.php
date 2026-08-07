@php use App\Models\ClockDevice; use App\Models\Outlet; @endphp

<div>
    @if (session()->has('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if (session()->has('warning'))
        <div class="alert-warning mb-4">{{ session('warning') }}</div>
    @endif

    <x-page-header eyebrow="HR / Clock-Ins" title="Clock kiosks">
        <x-slot:actions>
            <a href="{{ route('hr.clock-ins') }}" wire:navigate class="btn-secondary">Back to clock-ins</a>
            <a href="{{ route('hr.clock-settings') }}" wire:navigate class="btn-secondary">Settings</a>
        </x-slot:actions>
    </x-page-header>

    {{-- The pairing code.

         Pinned at the top and impossible to miss, because it is read aloud
         across a room to somebody holding a tablet and it is only good for a
         few minutes. It stays put across re-renders — a code that vanished on
         the next round trip would have to be reissued every time. --}}
    @if ($issuedCode !== '')
        <div class="panel mb-6 border-brand-200 bg-brand-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                        {{ $codeIsEnrol ? 'Enrolment code' : 'Pairing code' }}
                    </p>
                    <p class="mt-2 font-mono text-4xl font-bold tracking-[0.3em] text-brand-800">{{ $issuedCode }}</p>

                    @if ($codeIsEnrol)
                        <p class="help mt-3 max-w-xl">
                            On the kiosk, tap <strong>Enrol faces</strong> and key this in. It opens
                            enrolment for {{ ClockDevice::ENROL_WINDOW_MINUTES }} minutes and then
                            <strong>closes by itself</strong> — you do not have to remember to switch it off.
                            The code expires in {{ ClockDevice::PAIRING_TTL_MINUTES }} minutes and works once.
                        </p>
                        {{-- Enrolment is what every later punch is matched
                             against, so who is holding the tablet matters more
                             here than anywhere else in the feature. --}}
                        <p class="mt-2 max-w-xl text-xs text-warning-800">
                            Whoever holds the tablet during this window decides whose face is recorded
                            against which name. Stay with it.
                        </p>
                    @else
                        <p class="help mt-3 max-w-xl">
                            On the tablet, open
                            @if ($pairUrl)
                                <span class="font-semibold text-gray-900">{{ $pairUrl }}</span>
                            @else
                                the Staff Portal address followed by <span class="font-semibold">/kiosk/pair</span>
                            @endif
                            and key this in. It expires in {{ ClockDevice::PAIRING_TTL_MINUTES }} minutes,
                            and it can only be used once.
                        </p>
                    @endif
                </div>
                <button type="button" wire:click="dismissCode" class="btn-ghost">Done</button>
            </div>
        </div>
    @endif

    @if (! $settings->kiosk_enabled)
        <div class="alert-warning mb-6">
            Kiosk clock-in is switched off for this company, so paired tablets will refuse to work.
            Turn it on under <a href="{{ route('hr.clock-settings') }}" wire:navigate class="font-semibold underline">Clock-in settings</a>.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Add --}}
        <div class="lg:col-span-1">
            <form wire:submit="add" class="panel p-5">
                <h3 class="text-sm font-semibold text-gray-900">Add a kiosk</h3>
                <p class="help mt-1">
                    Name the tablet and choose where it lives. You will get a code to key into it.
                </p>

                <div class="mt-4">
                    <label for="kiosk-name" class="label">Name</label>
                    <input id="kiosk-name" type="text" wire:model="newName" class="input"
                           placeholder="Front counter iPad" maxlength="120">
                    @error('newName') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label for="kiosk-outlet" class="label">Outlet</label>
                    <select id="kiosk-outlet" wire:model="newOutletId" class="input">
                        <option value="">Choose an outlet</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    @error('newOutletId') <p class="error-text">{{ $message }}</p> @enderror
                    {{-- Said here rather than in a tooltip, because it is the
                         one thing on this form that cannot be changed later
                         without re-pairing, and it is what lets a kiosk punch
                         skip the geofence at all. --}}
                    <p class="help mt-1.5">
                        A kiosk vouches for this outlet, so punches made on it skip the GPS check entirely.
                    </p>
                </div>

                <button type="submit" class="btn-primary mt-5 w-full">
                    <span wire:loading.remove wire:target="add">Add and get a code</span>
                    <span wire:loading wire:target="add">Adding…</span>
                </button>
            </form>
        </div>

        {{-- List --}}
        <div class="lg:col-span-2">
            <div class="panel overflow-x-auto">
                <table class="table-surface">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Kiosk</th>
                            <th class="px-3 py-2 text-left">Outlet</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr wire:key="dev-{{ $device->id }}" class="{{ $device->isRevoked() ? 'opacity-60' : '' }}">
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    @if ($renamingId === $device->id)
                                        <div class="flex items-center gap-2">
                                            <input type="text" wire:model="renameValue" class="input py-1 text-sm" maxlength="120">
                                            <button type="button" wire:click="saveRename" class="btn-primary px-3 py-1 text-xs">Save</button>
                                            <button type="button" wire:click="cancelRename" class="btn-ghost px-2 py-1 text-xs">Cancel</button>
                                        </div>
                                    @else
                                        <span class="{{ $device->isRevoked() ? 'line-through' : '' }}">{{ $device->name }}</span>
                                        @if ($device->paired_at)
                                            <span class="block text-[11px] font-normal text-gray-500">
                                                Paired {{ $device->paired_at->format('d M Y') }}
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $device->outlet?->name ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        // Online is the only state that needs no
                                        // action, so it is the only green one.
                                        // "Waiting to pair" is amber rather than
                                        // neutral because somebody is standing
                                        // at a tablet right now waiting on it.
                                        $tone = match (true) {
                                            $device->isRevoked() => 'badge-neutral',
                                            $device->isOnline()  => 'badge-success',
                                            ! $device->isPaired() => 'badge-warning',
                                            default              => 'badge-danger',
                                        };
                                    @endphp
                                    <span class="{{ $tone }}">{{ $device->statusLabel() }}</span>

                                    @if ($device->isRevoked() && $device->revoker)
                                        <span class="block text-[11px] text-gray-500">by {{ $device->revoker->name }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @can('hr.clock.manage')
                                        @unless ($device->isRevoked())
                                            {{-- Only for a paired device: there is nothing to
                                                 enrol on a tablet that has not been set up. --}}
                                            @if ($device->isPaired())
                                                @if ($device->enrolmentOpen())
                                                    <button type="button" wire:click="endEnrolment({{ $device->id }})"
                                                            class="btn-ghost px-2 py-1 text-xs text-warning-700">
                                                        Enrolling ({{ $device->enrolmentMinutesLeft() }}m) — close
                                                    </button>
                                                @else
                                                    <button type="button" wire:click="enrolCode({{ $device->id }})"
                                                            class="btn-ghost px-2 py-1 text-xs text-brand-700">Enrol faces</button>
                                                @endif
                                            @endif
                                            <button type="button" wire:click="startRename({{ $device->id }})"
                                                    class="btn-ghost px-2 py-1 text-xs">Rename</button>
                                            <button type="button" wire:click="reissue({{ $device->id }})"
                                                    class="btn-ghost px-2 py-1 text-xs">
                                                {{ $device->isPaired() ? 'Re-pair' : 'New code' }}
                                            </button>
                                            <button type="button" wire:click="revoke({{ $device->id }})"
                                                    wire:confirm="Revoke {{ $device->name }}? It will stop working straight away and staff there will need to use their phones."
                                                    class="btn-ghost px-2 py-1 text-xs text-danger-600">Revoke</button>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-10">
                                    <div class="empty-state">
                                        <p class="font-semibold text-gray-900">No kiosks yet</p>
                                        <p class="help mt-1">
                                            Add one to let staff at an outlet clock in by face on a shared tablet,
                                            with no PIN and no GPS.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- The part people get wrong, said once, near the thing it is
                 about. Every line here is something that has to be true before
                 a kiosk works properly, and none of it is enforceable in code. --}}
            <div class="panel mt-4 p-5">
                <h3 class="text-sm font-semibold text-gray-900">Before a kiosk earns its keep</h3>
                <ul class="mt-3 space-y-2 text-sm text-gray-600">
                    <li>
                        <span class="font-medium text-gray-900">Enrol faces on the kiosk itself.</span>
                        A face enrolled on a manager's phone and matched against a tablet in different
                        light is the most common cause of "it does not recognise me".
                    </li>
                    <li>
                        <span class="font-medium text-gray-900">Set the outlet to kiosk only.</span>
                        Until you do, phone punches there are not flagged, and the phone wins on
                        convenience every time.
                    </li>
                    <li>
                        <span class="font-medium text-gray-900">Lock the tablet to this one app.</span>
                        Guided Access on iPad, screen pinning or a kiosk launcher on Android.
                        Keep it plugged in — the screen lock is held only while it is on charge.
                    </li>
                    <li>
                        <span class="font-medium text-gray-900">Watch this page.</span>
                        While a kiosk is offline, phone punches at that outlet stop being flagged —
                        deliberately, so a dead tablet cannot cost anyone their attendance, but it
                        does mean an unnoticed outage quietly switches the control off.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
