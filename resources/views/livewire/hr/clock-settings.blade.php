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

            {{-- Reverse geocoding. Off by default and spelled out, because
                 ticking it sends staff coordinates to a third party. --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model.live="resolve_addresses" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span class="text-sm">
                        <span class="font-medium text-gray-900">Look up a street address for each punch</span>
                        <span class="block text-xs text-gray-600">
                            Shows "12 Jalan Sultan Ismail" beside a punch instead of only its distance from the outlet.
                        </span>
                    </span>
                </label>

                @if ($resolve_addresses)
                    <div class="mt-3 pl-8 space-y-2">
                        <p class="text-xs text-gray-700">
                            <strong>What leaves the building:</strong> the coordinates of each punch are sent to
                            <strong>{{ $geocodeProvider }}</strong> to be turned into an address. No name, employee
                            number or photo is sent — only a latitude and longitude.
                        </p>
                        <p class="text-xs text-gray-600">
                            The lookup happens on the queue after the punch is recorded, so a slow or unavailable
                            provider never delays or blocks a clock-in. Results are cached per location, so a
                            doorway is looked up once no matter how many people punch there.
                        </p>
                        @unless ($geocodeReady)
                            <p class="text-xs text-danger-700 font-medium">
                                Google Maps is selected as the provider but no API key is configured, so no address
                                will be resolved.
                            </p>
                        @endunless
                        <div class="flex items-center gap-3 pt-1">
                            <button type="button" wire:click="testGeocoder" class="btn-secondary">
                                <span wire:loading.remove wire:target="testGeocoder">Test the provider</span>
                                <span wire:loading wire:target="testGeocoder">Testing…</span>
                            </button>
                            @if ($geocodeTest)
                                <span class="text-xs {{ $geocodeTest['ok'] ? 'text-success-700' : 'text-danger-700' }}">
                                    {{ $geocodeTest['ok'] ? $geocodeTest['address'] : $geocodeTest['message'] }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-gray-500">
                            Punches recorded before this was switched on keep their distance-only label until
                            someone runs <code>php artisan clock:backfill-addresses</code>.
                        </p>
                    </div>
                @endif
            </div>

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

    {{-- ── How staff clock in ───────────────────────────────────────────── --}}
    <div class="panel p-5 mt-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">How staff clock in</h3>
                <p class="help mt-1 max-w-2xl">
                    These switches say what the company allows at all. Which one an outlet actually
                    expects is set per outlet below, and individual staff can be excused from it on
                    their employee record.
                </p>
            </div>
            <a href="{{ route('hr.clock-devices') }}" wire:navigate class="btn-secondary">Manage kiosks</a>
        </div>

        <div class="mt-4 space-y-3">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="kiosk_enabled" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Allow outlet kiosks</span>
                    <span class="block text-xs text-gray-600">
                        A registered tablet on the counter recognises whoever walks up — no PIN, and
                        no GPS, because the device itself is what vouches for the outlet.
                        Turning this off stops every paired tablet within the minute.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model.live="kiosk_allow_pin" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Let the kiosk fall back to a PIN</span>
                    <span class="block text-xs text-gray-600">
                        When the camera cannot recognise somebody, they can find their name and key
                        their PIN instead. The offer only appears <em>after</em> a failed scan, never
                        as a standing option, and every PIN punch is flagged for your review.
                    </span>
                </span>
            </label>

            {{-- Shown only when they are switching it off, and it names the
                 casualty people do not expect. Everyone assumes the cost is
                 unenrolled new hires — a small group you can plan around. The
                 real cost is the enrolled person the camera cannot read this
                 morning, which is a Tuesday in a wet kitchen. --}}
            @unless ($kiosk_allow_pin)
                <div class="alert-warning ml-7">
                    With this off, anyone the camera cannot recognise has <strong>no way to clock in
                    at all</strong> — not only new hires who are not enrolled yet, but enrolled staff
                    in a hairnet, in steam, or in bad morning light. Their manager will have to mark
                    the attendance grid by hand. Enrol everybody on the kiosk itself first.
                </div>
            @endunless

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="byod_enabled" class="mt-0.5 rounded border-gray-300 text-brand-600">
                <span class="text-sm">
                    <span class="font-medium text-gray-900">Allow staff to clock in on their own phones</span>
                    <span class="block text-xs text-gray-600">
                        The Staff Portal, with GPS and a selfie. Leave this on unless every outlet has
                        a kiosk — area managers, drivers and offsite crews have nothing else.
                    </span>
                </span>
            </label>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Kiosk match threshold</label>
                <input type="number" min="0.30" max="0.60" step="0.01" wire:model="kiosk_face_threshold"
                       class="w-full rounded-lg border-gray-300 text-sm">
                @error('kiosk_face_threshold') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">
                    Tighter than the one above on purpose: a kiosk searches everybody at the outlet,
                    not one named person.
                </p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Clear-winner margin</label>
                <input type="number" min="0.02" max="0.30" step="0.01" wire:model="kiosk_face_margin"
                       class="w-full rounded-lg border-gray-300 text-sm">
                @error('kiosk_face_margin') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                {{-- The setting that actually prevents the bad outcome, so it
                     gets the sentence that explains what the bad outcome is. --}}
                <p class="mt-1 text-[11px] text-gray-500">
                    How far ahead of the runner-up the best match must be. Closer than this and the
                    kiosk asks for a PIN rather than guessing between two colleagues.
                </p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Kiosk cooldown (minutes)</label>
                <input type="number" min="0" max="120" wire:model="kiosk_cooldown_minutes"
                       class="w-full rounded-lg border-gray-300 text-sm">
                @error('kiosk_cooldown_minutes') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                <p class="mt-1 text-[11px] text-gray-500">
                    How long the kiosk ignores somebody it has just recorded, so walking past twice
                    does not clock them straight back out.
                </p>
            </div>
        </div>
    </div>

    {{-- Leaflet, for the pin picker. OpenStreetMap tiles: no key, no account,
         and the same source the address lookup already uses. Loaded here only,
         so no other page pays for it. --}}
    @once
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    @endonce

    {{-- ── Geofence ─────────────────────────────────────────────────────── --}}
    <div class="panel p-5" x-data="{
        pickerOpen: false,
        pickerOutlet: null,
        pickerName: '',
        pickerLat: null,
        pickerLng: null,
        map: null,
        marker: null,
        circle: null,

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
        },

        /* Drop a pin instead of typing coordinates. Opens on whatever the row
           already holds; failing that, on the first outlet that does have a
           position, so a new branch starts near its neighbours rather than in
           the Atlantic at 0,0. */
        openPicker(outletId, name, lat, lng, radius, fallback) {
            this.pickerOutlet = outletId;
            this.pickerName   = name;
            const start = (lat !== null && lng !== null) ? [lat, lng] : fallback;
            this.pickerLat = start[0];
            this.pickerLng = start[1];
            this.pickerOpen = true;

            this.$nextTick(() => {
                if (typeof L === 'undefined') return;

                if (! this.map) {
                    this.map = L.map(this.$refs.pickerMap);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(this.map);
                    this.map.on('click', (e) => this.setPin(e.latlng.lat, e.latlng.lng));
                }

                this.map.setView(start, (lat !== null && lng !== null) ? 18 : 12);
                this.setPin(start[0], start[1], radius);
                /* Leaflet measures the container on creation, and the modal was
                   display:none a moment ago — without this the tiles render
                   into a zero-sized box and the map looks broken. */
                setTimeout(() => this.map.invalidateSize(), 60);
            });
        },

        setPin(lat, lng, radius) {
            this.pickerLat = Number(lat.toFixed(7));
            this.pickerLng = Number(lng.toFixed(7));
            const metres = Number(radius ?? this.currentRadius()) || 150;

            if (! this.marker) {
                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    this.setPin(p.lat, p.lng);
                });
                // The circle is the geofence itself: seeing it is the only way
                // to tell a 20m radius from one that covers the car park.
                this.circle = L.circle([lat, lng], { radius: metres, color: '#0d9488', weight: 1, fillOpacity: 0.12 }).addTo(this.map);
            } else {
                this.marker.setLatLng([lat, lng]);
                this.circle.setLatLng([lat, lng]).setRadius(metres);
            }
        },

        currentRadius() {
            const el = this.$refs['rad' + this.pickerOutlet];
            return el ? Number(el.value) : 150;
        },

        applyPin() {
            const lat = this.$refs['lat' + this.pickerOutlet];
            const lng = this.$refs['lng' + this.pickerOutlet];
            lat.value = this.pickerLat.toFixed(7);
            lat.dispatchEvent(new Event('input'));
            lng.value = this.pickerLng.toFixed(7);
            lng.dispatchEvent(new Event('input'));
            this.$refs['status' + this.pickerOutlet].textContent = 'Set from the map.';
            this.pickerOpen = false;
        },
    }">
        <h3 class="text-sm font-semibold text-gray-900">Where each outlet is</h3>
        <p class="mt-0.5 text-xs text-gray-600">
            An outlet with no coordinates cannot check anybody is on site — its punches
            are flagged rather than quietly passed.
        </p>

        @php
            // Where the picker opens for an outlet with nothing set: the first
            // outlet that does have a position, else Kuala Lumpur.
            $fallback = collect($outlets)->first(fn ($o) => $o->latitude !== null && $o->longitude !== null);
            $fallbackPoint = $fallback
                ? [(float) $fallback->latitude, (float) $fallback->longitude]
                : [3.1390, 101.6869];
        @endphp

        <div class="mt-4 space-y-4">
            @foreach ($outlets as $outlet)
                @php
                    $lat = trim((string) ($fences[$outlet->id]['latitude'] ?? ''));
                    $lng = trim((string) ($fences[$outlet->id]['longitude'] ?? ''));
                    $hasPoint = $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng);
                @endphp
                <div wire:key="fence-{{ $outlet->id }}" class="rounded-lg border border-gray-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <p class="text-sm font-medium text-gray-900">{{ $outlet->name }}</p>
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($hasPoint)
                                {{-- Checking the pin landed somewhere sensible is the
                                     whole point; it opens where the outlet is set to,
                                     not where it is saved. --}}
                                <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}"
                                   target="_blank" rel="noopener noreferrer"
                                   class="text-xs font-medium text-brand-700 hover:underline">
                                    Show on map
                                </a>
                            @endif
                            <button type="button"
                                    @click="openPicker({{ $outlet->id }}, @js($outlet->name), {{ $hasPoint ? $lat : 'null' }}, {{ $hasPoint ? $lng : 'null' }}, {{ (int) ($fences[$outlet->id]['radius'] ?? 150) }}, @js($fallbackPoint))"
                                    class="text-xs font-medium text-brand-700 hover:underline">
                                Pick on map
                            </button>
                            <button type="button" @click="locate({{ $outlet->id }})"
                                    class="text-xs font-medium text-brand-700 hover:underline">
                                Use my current location
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                            {{-- Live so the "Show on map" link and the picker's
                                 starting point follow what is typed, not what
                                 was last saved. --}}
                            <input type="text" x-ref="lat{{ $outlet->id }}"
                                   wire:model.live.debounce.500ms="fences.{{ $outlet->id }}.latitude"
                                   class="w-full rounded-lg border-gray-300 text-sm tabular-nums">
                            @error('fences.' . $outlet->id . '.latitude') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                            <input type="text" x-ref="lng{{ $outlet->id }}"
                                   wire:model.live.debounce.500ms="fences.{{ $outlet->id }}.longitude"
                                   class="w-full rounded-lg border-gray-300 text-sm tabular-nums">
                            @error('fences.' . $outlet->id . '.longitude') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Radius (metres)</label>
                            <input type="number" min="20" max="5000" x-ref="rad{{ $outlet->id }}"
                                   wire:model.live.debounce.500ms="fences.{{ $outlet->id }}.radius"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                            @error('fences.' . $outlet->id . '.radius') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p x-ref="status{{ $outlet->id }}" class="mt-2 text-[11px] text-gray-500" aria-live="polite"></p>

                    {{-- Where this outlet's staff are expected to punch.

                         It sits with the geofence because the two answer the
                         same question from opposite ends: the fence is how a
                         phone proves it is here, and a kiosk is why one does
                         not have to. An outlet on kiosk_only is deliberately
                         still allowed a fence — the people with a standing
                         exception are using their phones, and their punches
                         should still be measured. --}}
                    <div class="mt-4 border-t border-gray-100 pt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Where staff here clock in
                        </label>
                        <select wire:model.live="fences.{{ $outlet->id }}.mode"
                                class="w-full rounded-lg border-gray-300 text-sm sm:max-w-sm">
                            @foreach (\App\Models\Outlet::PUNCH_MODE_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('fences.' . $outlet->id . '.mode') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[11px] text-gray-500">
                            {{ \App\Models\Outlet::PUNCH_MODE_HINTS[$fences[$outlet->id]['mode'] ?? \App\Models\Outlet::PUNCH_BYOD_ONLY] ?? '' }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end">
            <button wire:click="save" class="btn-primary">Save</button>
        </div>

        {{-- Pin picker. wire:ignore on the map itself: Livewire morphing the
             DOM under Leaflet leaves a dead grey box behind. --}}
        <div x-show="pickerOpen" x-cloak @keydown.escape.window="pickerOpen = false"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="pickerOpen = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Pick a location</h3>
                        <p class="text-xs text-gray-600" x-text="pickerName"></p>
                    </div>
                    <button @click="pickerOpen = false" class="text-gray-600 hover:text-gray-900 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <p class="text-xs text-gray-600">
                        Click the map to drop the pin, or drag it. The shaded circle is the geofence at the
                        radius set for this outlet — anyone punching outside it is flagged.
                    </p>

                    <div wire:ignore x-ref="pickerMap"
                         class="h-80 w-full rounded-lg border border-gray-200 bg-gray-100"></div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-gray-600 tabular-nums">
                            <span x-text="pickerLat !== null ? pickerLat.toFixed(7) : '—'"></span>,
                            <span x-text="pickerLng !== null ? pickerLng.toFixed(7) : '—'"></span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="pickerOpen = false" class="btn-secondary">Cancel</button>
                            <button type="button" @click="applyPin()" class="btn-primary">Use this location</button>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-500">
                        Map tiles come from OpenStreetMap, so opening this contacts them from your browser.
                        Nothing is sent from the server and the pin is only saved when you press Save.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
