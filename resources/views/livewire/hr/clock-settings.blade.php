<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Clock-In Settings</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Clock-In Settings</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr.clock-ins') }}" wire:navigate class="btn-secondary">Back to Clock-Ins</a>
            <button wire:click="save" class="btn-primary">Save</button>
        </div>
    </div>

    {{-- ── Lateness ─────────────────────────────────────────────────────
         First, because it is the only section that costs anybody money. --}}
    <div class="panel p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-900">Lateness</h3>
        <p class="mt-0.5 text-xs text-gray-600">
            Measured against the rostered start time on the <strong>approved</strong> duty roster.
            A shift on a draft roster is never charged for.
        </p>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Grace (minutes)</label>
                <input type="number" min="0" max="240" wire:model="grace_minutes" class="w-full rounded-lg border-gray-300 text-sm">
                @error('grace_minutes') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">Minutes forgiven before anything is charged.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Charge per minute (RM)</label>
                <input type="number" min="0" step="0.01" wire:model="late_rate_per_minute" class="w-full rounded-lg border-gray-300 text-sm">
                @error('late_rate_per_minute') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">0 tracks lateness without charging for it.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cap per shift (RM)</label>
                <input type="number" min="0" step="0.01" wire:model="late_cap_per_shift" placeholder="No cap" class="w-full rounded-lg border-gray-300 text-sm">
                @error('late_cap_per_shift') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">Stops one bad morning wiping out a month.</p>
            </div>
        </div>

        <div class="mt-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-xs text-gray-700">
            The charge is deducted from the employee's service charge share on the
            Attendance Record screen, after the MC and absent percentages, and never
            takes a share below zero. One charge per shift, however many times somebody
            taps the button.
            @if ((float) $late_rate_per_minute > 0)
                <span class="block mt-1 font-medium text-gray-900">
                    As set: {{ (int) $grace_minutes }} minutes free, then
                    RM {{ number_format((float) $late_rate_per_minute, 2) }} a minute —
                    arriving 20 minutes late costs
                    RM {{ number_format(
                        min(
                            max(0, 20 - (int) $grace_minutes) * (float) $late_rate_per_minute,
                            $late_cap_per_shift !== '' ? (float) $late_cap_per_shift : PHP_FLOAT_MAX
                        ), 2) }}.
                </span>
            @endif
        </div>

        <div class="mt-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Earliest clock-in (minutes before the shift)</label>
            <input type="number" min="0" max="1440" wire:model="early_window_minutes" class="w-full sm:w-48 rounded-lg border-gray-300 text-sm">
            @error('early_window_minutes') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            <p class="mt-1 text-[11px] text-gray-500">Earlier than this is still recorded, but flagged for review.</p>
        </div>
    </div>

    {{-- ── Checks ───────────────────────────────────────────────────────── --}}
    <div class="panel p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-900">Checks</h3>

        <div class="mt-3 space-y-3">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="require_gps" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Require location</span>
                    <span class="block text-xs text-gray-600">Without a location fix the punch is refused, not just flagged.</span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="allow_offsite_with_reason" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Allow clocking in off-site with a reason</span>
                    <span class="block text-xs text-gray-600">Recommended — staff do get sent to other branches. The punch is still flagged for you.</span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="require_face" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Require a face capture</span>
                    <span class="block text-xs text-gray-600">
                        Refuses a punch when no readable face was captured at all — a covered lens
                        or a dark doorway. Whether a face that <em>is</em> captured has to match is
                        the separate setting below.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="require_face_match" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Refuse a face that does not match</span>
                    <span class="block text-xs text-gray-600">
                        Off, a mismatch is recorded and flagged for you to review, so a new beard or
                        a bad light never costs somebody a day's attendance. On, they are turned away
                        at the door and no punch is recorded.
                    </span>
                    <span class="block mt-1 text-xs text-amber-700">
                        This is the only setting here that can send somebody home. Before turning it
                        on, check Face Enrolment: anyone whose captures are flagged as odd is
                        somebody this will refuse. Staff with no face enrolled are never refused —
                        there is nothing to match them against.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="mark_attendance" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Mark Present on the attendance grid</span>
                    <span class="block text-xs text-gray-600">Only fills blank days. A day you have already marked is left alone.</span>
                </span>
            </label>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Worst GPS accuracy trusted (metres)</label>
                <input type="number" min="5" max="5000" wire:model="max_accuracy_m" class="w-full rounded-lg border-gray-300 text-sm">
                @error('max_accuracy_m') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">A vaguer fix than this is flagged — indoors it is routinely worse.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Face match threshold</label>
                <input type="number" min="0.30" max="0.70" step="0.01" wire:model="face_threshold" class="w-full rounded-lg border-gray-300 text-sm">
                @error('face_threshold') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">
                    Lower is stricter. 0.50 is a good default; 0.60 is the model's own published line.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Geofence ─────────────────────────────────────────────────────── --}}
    <div class="panel p-5" x-data="{
        /* Fills the pair from the browser's own position. Meant to be pressed
           while standing at the outlet — which is the only way to get these
           right without hunting coordinates on a map. */
        locate(outletId) {
            const status = this.$refs['status' + outletId];
            if (! navigator.geolocation) { status.textContent = 'This browser has no location.'; return; }
            status.textContent = 'Locating…';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    this.$refs['lat' + outletId].value = p.coords.latitude.toFixed(7);
                    this.$refs['lat' + outletId].dispatchEvent(new Event('input'));
                    this.$refs['lng' + outletId].value = p.coords.longitude.toFixed(7);
                    this.$refs['lng' + outletId].dispatchEvent(new Event('input'));
                    status.textContent = 'Set to here (±' + Math.round(p.coords.accuracy) + 'm).';
                },
                () => { status.textContent = 'Location refused.'; },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 },
            );
        }
    }">
        <h3 class="text-sm font-semibold text-gray-900">Where each outlet is</h3>
        <p class="mt-0.5 text-xs text-gray-600">
            An outlet with no coordinates cannot check anybody is on site — its punches
            are flagged rather than quietly passed.
        </p>

        <div class="mt-4 space-y-4">
            @foreach ($outlets as $outlet)
                <div wire:key="fence-{{ $outlet->id }}" class="rounded-lg border border-gray-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <p class="text-sm font-medium text-gray-900">{{ $outlet->name }}</p>
                        <button type="button" @click="locate({{ $outlet->id }})"
                                class="text-xs font-medium text-brand-700 hover:underline">
                            Use my current location
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                            <input type="text" x-ref="lat{{ $outlet->id }}"
                                   wire:model="fences.{{ $outlet->id }}.latitude"
                                   class="w-full rounded-lg border-gray-300 text-sm tabular-nums">
                            @error('fences.' . $outlet->id . '.latitude') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                            <input type="text" x-ref="lng{{ $outlet->id }}"
                                   wire:model="fences.{{ $outlet->id }}.longitude"
                                   class="w-full rounded-lg border-gray-300 text-sm tabular-nums">
                            @error('fences.' . $outlet->id . '.longitude') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Radius (metres)</label>
                            <input type="number" min="20" max="5000" wire:model="fences.{{ $outlet->id }}.radius"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                            @error('fences.' . $outlet->id . '.radius') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p x-ref="status{{ $outlet->id }}" class="mt-2 text-[11px] text-gray-500" aria-live="polite"></p>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end">
            <button wire:click="save" class="btn-primary">Save</button>
        </div>
    </div>
</div>
