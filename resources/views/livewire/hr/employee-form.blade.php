@php
    // Which tab each field belongs to, so a validation failure on a hidden tab
    // can be flagged rather than leaving someone staring at a form that will
    // not save with nothing visibly wrong on it.
    $tabFields = [
        'personal'   => ['f_name', 'f_ic_number', 'f_date_of_birth', 'f_email', 'f_phone', 'f_phone_code',
                         'f_passport_number', 'f_passport_expired_on', 'f_visa_number', 'f_visa_expired_on',
                         'f_home_address', 'f_mailing_address',
                         'photo', 'f_gender', 'f_nationality', 'f_race', 'f_religion', 'f_marital_status',
                         'f_education_level', 'f_emergency_contact_name', 'f_emergency_contact_relationship',
                         'f_emergency_contact_phone', 'f_emergency_contact_phone_alt', 'f_emergency_contact_address',
                         // Where the salary is paid, not what it is.
                         'f_bank_name', 'f_bank_account_no', 'f_bank_account_name'],
        'employment' => ['f_outlet_id', 'f_section_id', 'f_staff_id', 'f_designation', 'f_join_date',
                         'f_employment_status', 'f_employment_status_date', 'f_outsourcing_provider',
                         'f_outsourcing_company', 'f_break_minutes'],
        'pay'        => ['f_basic_salary', 'f_pay_type', 'f_service_points',
                         // Each of these decides what somebody is paid or where
                         // it comes from, so they sit behind the same door.
                         'f_allow_byod', 'f_allow_anywhere', 'f_overtime_as_time_off',
                         'f_service_charge_outlet_id'],
        'statutory'  => ['s_epf_number', 's_socso_number', 's_tax_number', 's_epf_override',
                         's_pcb_category', 's_children', 's_zakat', 's_other_relief'],
        'compliance' => ['f_food_handler_cert_no', 'f_food_handler_expired_on', 'f_typhoid_valid_from',
                         'f_typhoid_expired_on', 'f_halal_training_date', 'f_halal_training_expired_on',
                         'f_certifications'],
    ];

    $tabErrors = [];
    foreach ($tabFields as $tabKey => $fields) {
        $tabErrors[$tabKey] = collect($errors->keys())
            ->contains(fn ($k) => collect($fields)->contains(fn ($f) => $k === $f || str_starts_with($k, $f . '.')));
    }

    $tabs = [
        'personal'   => 'Personal',
        'employment' => 'Employment',
    ];
    if ($canViewPay) {
        $tabs['pay']       = 'Compensation';
        $tabs['statutory'] = 'Statutory';
    }
    $tabs['compliance'] = 'Certifications';
    if ($employeeId) {
        // Uploads need a record to hang off, so this tab only exists on edit.
        $tabs['documents'] = 'Documents';
        $tabs['activity']  = 'Activity';
    }

    // Open on the first tab carrying an error, so a failed save lands where the
    // problem is instead of on whichever tab happens to be first.
    $openTab = collect(array_keys($tabs))->first(fn ($t) => $tabErrors[$t] ?? false) ?? 'personal';

    /*
     * Read off the FORM's status rather than the saved employee's, because
     * f_employment_status is wire:model.live: switching somebody to Outsourcing
     * shuts the statutory section and relabels the salary field there and then,
     * rather than after a save. The saved record answers the same question
     * through Employee::isOutsourced(), which is what the calculation uses.
     */
    $isOutsourced = $f_employment_status === 'outsourcing';
    $isIntern     = $f_employment_status === 'internship';

    /*
     * Both end up outside statutory, for different reasons — the agent is the
     * employer of record in one case, and an internship is paid by stipend in
     * the other. See Employee::hasNoStatutoryContribution(), which is what the
     * calculation actually uses.
     */
    $noStatutory = $isOutsourced || $isIntern;
@endphp

<div x-data="{ tab: @js($openTab) }">
    {{-- Header.

         THE ACTION ROW USED TO BE `flex items-center gap-2` WITH NO WRAP, and
         on a phone that is not a cosmetic problem: four buttons carrying
         `whitespace-nowrap` measured 566px inside a 390px viewport, so Cancel
         and Save sat entirely OFF-SCREEN. They were reachable only by
         discovering that the page could be dragged sideways — `main` is
         `overflow-y-auto`, and CSS computes the other axis of a scroll
         container to `auto` as well, so the whole form scrolled horizontally
         instead of visibly breaking. Somebody filling this in on the floor
         could not save it.

         min-w-0 on the title block is the other half. A flex item defaults to
         min-width:auto, which refuses to shrink below its content, so a long
         name — and Malaysian names on this system run to five and six words —
         pushed the row wider still rather than wrapping inside it. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex min-w-0 items-start gap-3">
            @canDo('hr.view')
            <a data-back href="{{ route('hr.employees', $this->returnParams()) }}" title="Back to Employees"
               class="mt-0.5 flex-shrink-0 p-1.5 rounded-control text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            @endcanDo
            <div class="min-w-0">
                <p class="text-xs text-gray-600">HR / Employees</p>
                <h2 class="text-lg font-semibold text-gray-700 mt-1 break-words">
                    {{ $employeeId ? ($f_name ?: 'Edit Employee') : 'Add Employee' }}
                </h2>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($employeeId && $canViewPay)
                <a href="{{ route('hr.compensation.employee', $employeeId) }}" class="btn-secondary">
                    Allowances &amp; salary history
                </a>
            @endif
            @if ($employeeId)
                {{-- Opens in a tab rather than downloading: this is usually
                     printed to be signed, and a tab is one step closer to a
                     printer than a file in Downloads. --}}
                <a href="{{ route('hr.employees.details-pdf', $employeeId) }}" target="_blank" rel="noopener"
                   class="btn-secondary">
                    Print details
                </a>
            @endif
            @canDo('hr.view')
            <a href="{{ route('hr.employees', $this->returnParams()) }}" class="btn-secondary">Cancel</a>
            @endcanDo
            <button type="submit" form="employee-form" class="btn-primary">Save</button>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabs. Every panel stays in the DOM under x-show rather than @if, so a
         value typed on one tab is still in the payload when Save is pressed
         from another — unmounting the inputs would silently drop them. --}}
    <div class="border-b border-gray-200 mb-4 overflow-x-auto">
        <nav class="flex gap-1 min-w-max" aria-label="Employee sections">
            @foreach ($tabs as $key => $label)
                <button type="button" x-on:click="tab = @js($key)"
                        :class="tab === @js($key)
                            ? 'border-brand-600 text-brand-700'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300'"
                        class="relative px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap">
                    {{ $label }}
                    @if ($tabErrors[$key] ?? false)
                        <span title="This section has an error"
                              class="absolute top-1.5 right-1 h-1.5 w-1.5 rounded-full bg-danger-500"></span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <form id="employee-form" wire:submit.prevent="save" class="space-y-4">

        {{-- ── Personal ────────────────────────────────────────────────── --}}
        <div x-show="tab === 'personal'" x-cloak class="space-y-4">
        <div class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Personal</h3>
                <p class="text-xs text-gray-500">Who this person is. The IC and date of birth drive statutory rates and every submission.</p>
            </div>

            {{-- Photograph and name sit together: it is how anyone checks they
                 have opened the right record. --}}
            @php
                /* Resolved once: the thumbnail and the enlarged view must be the
                   same image, and a freshly-uploaded photo has a different source
                   from a stored one.

                   The extension check is load-bearing, not fussy: temporaryUrl()
                   THROWS for anything Livewire cannot preview (an iPhone HEIC,
                   before updatedPhoto() converts it), and a throw here is a 500
                   on every render until the state is abandoned — which is
                   exactly the dead form that was reported. A file the check
                   rejects falls back to the stored photo. */
                $photoPreviewable = $photo && in_array(
                    strtolower($photo->getClientOriginalExtension()),
                    ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    true,
                );

                $photoSrc = $photoPreviewable
                    ? $photo->temporaryUrl()
                    : (($photoPath && $employeeId && (auth()->user()?->canDo('hr.view') ?? false))
                        ? route('hr.employees.photo', $employeeId)
                        : null);
            @endphp

            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 text-center" x-data="{ zoom: false }">
                    <div class="h-20 w-20 rounded-full overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                        @if ($photoSrc)
                            {{-- 80px of face is enough to know you have the right
                                 record and not enough to check it against the person
                                 in front of you, which is what this is for. --}}
                            <button type="button" @click="zoom = true"
                                    title="{{ $f_name ? 'View ' . $f_name . '’s photo larger' : 'View the photo larger' }}"
                                    class="h-full w-full cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                <img src="{{ $photoSrc }}" alt="" class="h-full w-full object-cover" />
                            </button>
                        @else
                            <x-icon name="users" class="h-7 w-7 text-gray-400" />
                        @endif
                    </div>
                    <div wire:loading wire:target="photo" class="mt-1 text-[11px] text-gray-500">Uploading…</div>

                    @if ($photoSrc)
                        {{-- Teleported to body at z-[100], like every other modal in
                             HR: this form sits inside an overflow-y-auto main, which
                             clips a fixed overlay, and the sidebar is z-50. --}}
                        <template x-teleport="body">
                            <div x-show="zoom" x-cloak @keydown.escape.window="zoom = false"
                                 class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                                <div class="fixed inset-0 bg-black/70" @click="zoom = false"></div>
                                <div class="relative max-h-full" @click.stop>
                                    <img src="{{ $photoSrc }}" alt="{{ $f_name ?: 'Employee photo' }}"
                                         class="max-h-[80vh] max-w-[90vw] rounded-panel shadow-e4 object-contain bg-white" />
                                    <button type="button" @click="zoom = false"
                                            title="Close"
                                            class="absolute -top-3 -right-3 h-9 w-9 rounded-full bg-white text-gray-700 shadow-e2 flex items-center justify-center hover:text-gray-900">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    @if ($f_name)
                                        <p class="mt-3 text-center text-sm text-white/90">{{ $f_name }}</p>
                                    @endif
                                </div>
                            </div>
                        </template>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <label class="text-xs font-semibold text-gray-600">Employee Name <span class="text-danger-500">*</span></label>
                    <input type="text" wire:model="f_name" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('f_name')" class="mt-1" />

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <label class="text-xs font-medium text-brand-600 hover:text-brand-800 cursor-pointer">
                            {{ $photoPath || $photo ? 'Change photo' : 'Add photo' }}
                            <input type="file" wire:model="photo" accept="image/*" class="hidden" />
                        </label>
                        @if ($photoPath || $photo)
                            <button type="button" wire:click="removePhoto"
                            data-confirm-delete="Remove this employee's photograph? The image file is deleted from disk and cannot be recovered — it would have to be taken and uploaded again."
                                    class="text-xs font-medium text-danger-600 hover:text-danger-800">Remove</button>
                        @endif
                        <span class="text-[11px] text-gray-500">
                            Kept private — only staff who can open this record can see it. Up to 5 MB.
                        </span>
                    </div>
                    <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">IC No.</label>
                    <input type="text" maxlength="20" wire:model="f_ic_number"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 880101-10-5555" />
                    <p class="mt-1 text-[11px] text-gray-500">Identifies the employee on CP39, KWSP, SOCSO and the EA form.</p>
                    <x-input-error :messages="$errors->get('f_ic_number')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Date of Birth</label>
                    <input type="date" wire:model="f_date_of_birth" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">EPF and SOCSO rates change at 60, and EIS stops.</p>
                    <x-input-error :messages="$errors->get('f_date_of_birth')" class="mt-1" />
                </div>
            </div>

            {{-- Passport and visa.

                 ALWAYS SHOWN, not revealed by nationality. A field that hides
                 itself hides whatever was already typed into it, and plenty of
                 local staff hold a passport too. Both are optional, and a blank
                 one is not reported as missing — only a date that is running
                 out gets a reminder. --}}
            <div class="rounded-surface border border-gray-200 p-4 space-y-3">
                <div>
                    <h4 class="text-xs font-semibold text-gray-700">Passport &amp; Visa</h4>
                    <p class="text-[11px] text-gray-500">
                        For foreign staff. Both expiries are watched on the Employees screen
                        alongside the certification reminders, so a permit running out shows up
                        before the day it does.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Passport No.</label>
                        <input type="text" maxlength="50" wire:model="f_passport_number"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. A12345678" />
                        <x-input-error :messages="$errors->get('f_passport_number')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Passport Expiry</label>
                        <input type="date" wire:model="f_passport_expired_on"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_passport_expired_on')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Visa / Work Permit No.</label>
                        <input type="text" maxlength="50" wire:model="f_visa_number"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_visa_number')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Visa / Work Permit Expiry</label>
                        <input type="date" wire:model="f_visa_expired_on"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_visa_expired_on')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">E-mail</label>
                    <input type="email" wire:model="f_email" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">Where their payslip is emailed, if you send them.</p>
                    <x-input-error :messages="$errors->get('f_email')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Phone</label>
                    <div class="mt-1 flex gap-2">
                        <select wire:model="f_phone_code" class="w-28 flex-shrink-0 text-sm rounded-lg border-gray-300">
                            @foreach (\App\Models\Employee::PHONE_COUNTRY_CODES as $iso => $dial)
                                <option value="{{ $dial }}">{{ $iso }} {{ $dial }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="f_phone" class="w-full text-sm rounded-lg border-gray-300" placeholder="12 345 6789" />
                    </div>
                    <x-input-error :messages="$errors->get('f_phone_code')" class="mt-1" />
                    <x-input-error :messages="$errors->get('f_phone')" class="mt-1" />
                </div>
            </div>

            {{-- Particulars. Every list is managed in Settings, so a company
                 that needs a nationality nobody thought of can add it. --}}
            <div class="pt-3 border-t border-gray-100">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach (['gender', 'nationality', 'race', 'religion', 'marital_status', 'education_level'] as $type)
                        {{-- Block form, not @php(...): this file has @php…@endphp
                             blocks, and Blade's raw-block pass would swallow
                             everything between an inline one and the next
                             @endphp. --}}
                        @php $prop = \App\Livewire\Hr\EmployeeForm::PARTICULARS[$type]; @endphp
                        <div wire:key="particular-{{ $type }}">
                            <label class="text-xs font-semibold text-gray-600">
                                {{ \App\Models\HrOption::labelFor($type) }}
                            </label>
                            <select wire:model="{{ $prop }}" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="">— Not stated —</option>
                                @foreach ($particulars[$type] as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get($prop)" class="mt-1" />
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-gray-500">
                    Missing an option?
                    @canDo('settings.hr')<a href="{{ route('settings.employee-particulars') }}" class="text-brand-600 hover:text-brand-800 font-medium">Manage these lists</a>@endcanDo
                </p>
            </div>
        </div>

        {{-- Address. Its own card because it is two related answers, not one
             field: where they live, and — only when it differs — where post
             actually goes. Anything that has to be sent to an employee, an EA
             form or a letter of employment, reads the second one. --}}
        <div class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Address</h3>
                <p class="text-xs text-gray-500">Where they live, and where to post things.</p>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600">Home Address</label>
                <textarea rows="3" maxlength="500" wire:model="f_home_address"
                          placeholder="Unit / street, taman, postcode, town, state"
                          class="mt-1 w-full text-sm rounded-lg border-gray-300"></textarea>
                <x-input-error :messages="$errors->get('f_home_address')" class="mt-1" />
            </div>

            {{-- .live so the second field appears the moment the box is
                 cleared, rather than after a save. --}}
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="f_mailing_same"
                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Mailing address is the same as home</span>
            </label>

            @unless ($f_mailing_same)
                <div>
                    <label class="text-xs font-semibold text-gray-600">
                        Mailing Address <span class="text-danger-500">*</span>
                    </label>
                    <textarea rows="3" maxlength="500" wire:model="f_mailing_address"
                              placeholder="Where post should go instead"
                              class="mt-1 w-full text-sm rounded-lg border-gray-300"></textarea>
                    {{-- Says which one wins, because two addresses on a record
                         with no stated precedence is how a letter goes to the
                         wrong one. --}}
                    <p class="mt-1 text-[11px] text-gray-600">
                        Anything posted to this employee goes here rather than to their home address.
                    </p>
                    <x-input-error :messages="$errors->get('f_mailing_address')" class="mt-1" />
                </div>
            @endunless
        </div>

        {{-- Emergency contact. Its own card: it is the block somebody reads in
             a hurry, and burying it under the phone number costs seconds that
             matter. --}}
        <div class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Emergency Contact</h3>
                <p class="text-xs text-gray-500">Who to call if something happens at work.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Name</label>
                    <input type="text" maxlength="120" wire:model="f_emergency_contact_name"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('f_emergency_contact_name')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Relationship</label>
                    <select wire:model="f_emergency_contact_relationship" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">— Not stated —</option>
                        @foreach ($particulars['relationship'] as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('f_emergency_contact_relationship')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Phone</label>
                    <input type="text" maxlength="50" wire:model="f_emergency_contact_phone"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. +60 12 345 6789" />
                    <x-input-error :messages="$errors->get('f_emergency_contact_phone')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Second Phone</label>
                    <input type="text" maxlength="50" wire:model="f_emergency_contact_phone_alt"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">The one person listed is often at work themselves.</p>
                    <x-input-error :messages="$errors->get('f_emergency_contact_phone_alt')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600">Address</label>
                <input type="text" maxlength="255" wire:model="f_emergency_contact_address"
                       class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('f_emergency_contact_address')" class="mt-1" />
            </div>
        </div>

        {{-- ── Banking ─────────────────────────────────────────────────────
             Where the salary is paid, NOT what it is.

             Moved off the Compensation tab so that somebody who keeps staff
             records current can fix an account number without being shown the
             company's payroll to do it. The two were only ever together
             because payroll reads them in the same breath.
        --}}
        <div class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Banking</h3>
                <p class="text-xs text-gray-500">Where this person's salary is paid.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Bank</label>
                    {{-- Shown uppercase, stored verbatim.

                         The seeded IBG list has the casing of the published
                         document, which is ragged — "Affin Bank" sits three
                         rows above "AFFIN ISLAMIC BANK BHD" — and in a
                         dropdown that reads as a list somebody half finished
                         editing. The VALUE stays exactly as the banks table
                         holds it, because it is matched against that table on
                         save and against existing employee records on load;
                         uppercasing the value would silently orphan every
                         bank already on file. --}}
                    <select wire:model.live="f_bank_name" class="mt-1 w-full text-sm rounded-lg border-gray-300 uppercase">
                        <option value="" class="normal-case">— Select bank —</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->name }}">{{ Str::upper($bank->name) }}</option>
                        @endforeach
                    </select>
                    @php $pickedBank = $banks->firstWhere('name', $f_bank_name); @endphp
                    @if ($pickedBank?->bic)
                        <p class="mt-1 text-[11px] text-gray-500 font-mono">{{ $pickedBank->bic }}</p>
                    @endif
                    {{-- The bank list is company settings, which is a
                         different permission from editing an employee. --}}
                    @can('hr.compensation')
                        <p class="mt-1 text-[11px] text-gray-500">
                            Missing one? <a href="{{ route('settings.banks') }}" class="text-brand-600 hover:text-brand-800 font-medium">Manage banks</a>
                        </p>
                    @endcan
                    <x-input-error :messages="$errors->get('f_bank_name')" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600">Bank Account No.</label>
                    <input type="text" maxlength="40" wire:model="f_bank_account_no"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300 font-mono" />
                    <p class="mt-1 text-[11px] text-gray-500">Needed for the salary payment file.</p>
                    <x-input-error :messages="$errors->get('f_bank_account_no')" class="mt-1" />
                </div>
            </div>

            {{-- Its own row, below the account number and not beside it, so
                 the empty state reads as "nothing to do here" rather than as
                 a third of a form somebody forgot to fill in. --}}
            <div>
                <label class="text-xs font-semibold text-gray-600">Bank Account Name</label>
                <input type="text" maxlength="120" wire:model="f_bank_account_name"
                       class="mt-1 w-full text-sm rounded-lg border-gray-300 uppercase"
                       placeholder="Leave blank if the account is in {{ $f_name !== '' ? $f_name : 'the employee’s' }} own name" />
                <p class="mt-1 text-[11px] text-gray-500">
                    Only if the salary goes into someone else's account — a spouse's or a parent's.
                    The bank matches this name against the account number and rejects the transfer
                    if they disagree, so the employee's own name here would fail the payment.
                </p>
                <x-input-error :messages="$errors->get('f_bank_account_name')" class="mt-1" />
            </div>
        </div>
        </div>

        {{-- ── Employment ──────────────────────────────────────────────── --}}
        <div x-show="tab === 'employment'" x-cloak class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Employment</h3>
                <p class="text-xs text-gray-500">Where they work, what they do, and their standing.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Outlet <span class="text-danger-500">*</span></label>
                    <select wire:model="f_outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">— Select —</option>
                        @foreach ($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('f_outlet_id')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Section</label>
                    <select wire:model="f_section_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">— None —</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('f_section_id')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Staff ID</label>
                    <input type="text" wire:model="f_staff_id" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. EMP-001" />
                    <x-input-error :messages="$errors->get('f_staff_id')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Designation</label>
                    <input type="text" wire:model="f_designation" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Kitchen Helper" />
                </div>
            </div>

            {{-- Employment standing — status, resignation date, join date, active and
                 outsourcing. Behind hr.employment, the same way Compensation sits behind
                 hr.compensation. The placement fields above stay open on purpose: outlet
                 is required to save, so hiding those too would leave anyone who can edit
                 staff with a form they cannot submit. --}}
            @if ($canEditEmployment)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Join Date</label>
                    <input type="date" wire:model="f_join_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('f_join_date')" class="mt-1" />
                </div>
                @php
                    // Mirrors what save() enforces, so the checkbox never
                    // silently disagrees with what gets written.
                    $resignationLanded = \App\Models\Employee::resignationTookEffect(
                        $f_employment_status ?: null,
                        $f_employment_status === 'resigned' ? ($f_employment_status_date ?: null) : null,
                    );
                @endphp
                <div class="flex flex-col justify-end pb-1">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" @disabled($resignationLanded)
                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 disabled:opacity-50" />
                        <span class="text-sm {{ $resignationLanded ? 'text-gray-500' : 'text-gray-700' }}">Active</span>
                    </label>
                    @if ($resignationLanded)
                        <p class="text-[11px] text-gray-500 mt-1">
                            Resignation date has passed — this employee is set inactive and drops off
                            attendance, roster and service charge from the next period.
                        </p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-gray-100">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employment Status</label>
                    <select wire:model.live="f_employment_status" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">— None —</option>
                        @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $esValue => $esLabel)
                            <option value="{{ $esValue }}">{{ $esLabel }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('f_employment_status')" class="mt-1" />
                </div>
                @if (array_key_exists($f_employment_status, \App\Models\Employee::EMPLOYMENT_STATUS_DATE_LABELS))
                    <div>
                        <label class="text-xs font-semibold text-gray-600">
                            {{ \App\Models\Employee::EMPLOYMENT_STATUS_DATE_LABELS[$f_employment_status] }}
                            <span class="text-danger-500">*</span>
                        </label>
                        {{-- .live so the Active checkbox above reflects a past
                             resignation date as soon as it is entered. --}}
                        <input type="date" wire:model.live="f_employment_status_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_employment_status_date')" class="mt-1" />
                    </div>
                @elseif ($f_employment_status === 'outsourcing')
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Outsourcing Company</label>
                        <select wire:model.live="f_outsourcing_provider" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                            <option value="experiva">Experiva</option>
                            <option value="others">Others</option>
                        </select>
                        @if ($f_outsourcing_provider === 'others')
                            <input type="text" wire:model="f_outsourcing_company" class="mt-2 w-full text-sm rounded-lg border-gray-300" placeholder="Company name" />
                        @endif
                        <x-input-error :messages="$errors->get('f_outsourcing_company')" class="mt-1" />
                    </div>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Reports to</label>
                    <select wire:model="f_reports_to_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">— None —</option>
                        @foreach ($superiors as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Their superior. Leave and time off requests are emailed to this person for approval.
                        With nobody set, requests fall back to
                        @canDo('settings.hr')<a href="{{ route('settings.leave-approvers') }}" class="text-brand-600 hover:underline">Settings → Leave Approvers</a>@else<span>the company default</span>@endcanDo.
                    </p>
                    <x-input-error :messages="$errors->get('f_reports_to_id')" class="mt-1" />
                </div>
                <div></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Working day (hours)</label>
                    <input type="number" step="0.25" min="1" max="24" wire:model="f_daily_working_hours"
                           placeholder="Company default"
                           class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">
                        This employee's contract day. Used to show what a block of time off comes to in days —
                        7.5, 9 and 11-hour contracts are all different amounts of overtime. Blank follows the
                        company default.
                    </p>
                    <x-input-error :messages="$errors->get('f_daily_working_hours')" class="mt-1" />
                </div>
            </div>

            <div class="sm:w-1/2 sm:pr-1.5">
                <label class="text-xs font-semibold text-gray-600">Break Allowance (minutes)</label>
                <input type="number" min="0" max="1440" wire:model="f_break_minutes"
                       placeholder="Use the roster's allowance"
                       class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('f_break_minutes')" class="mt-1" />
                {{-- Blank and 0 mean different things, so both are spelled out. --}}
                <p class="mt-1 text-[11px] text-gray-500">
                    A personal override for clock-in break tracking. Leave blank to follow the duty
                    roster's rest duration for each shift. Enter 0 for no break allowance at all.
                </p>
            </div>
        </div>

        {{-- ── Compensation ────────────────────────────────────────────── --}}
        @if ($canViewPay)
            <div x-show="tab === 'pay'" x-cloak class="card p-5 space-y-3">
                {{-- ── Clock-in and settlement ─────────────────────────────
                     Kept behind the same door as pay because each of these
                     four decides what somebody is PAID or where it comes
                     from: two of them waive a control on the punch that
                     becomes their hours, one sends approved overtime to a
                     balance instead of a payslip, and the last moves a person
                     between service charge pools.
                --}}
                <div class="flex flex-wrap -mx-1.5">
                    <div class="sm:w-1/2 sm:pr-1.5">
                    <label class="text-xs font-semibold text-gray-600">Clock in on own phone</label>
                    <select wire:model="f_allow_byod" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">Follow the outlet</option>
                        <option value="yes">Always allowed</option>
                        <option value="no">Never — kiosk only</option>
                    </select>
                    <x-input-error :messages="$errors->get('f_allow_byod')" class="mt-1" />
                    {{-- The exception, and what it is for. Left on "follow the
                         outlet", moving that outlet onto its kiosk moves this
                         person with it; set explicitly, it does not. --}}
                    <p class="mt-1 text-[11px] text-gray-500">
                        At an outlet set to kiosk only, a punch from somebody's own phone is still
                        recorded but is flagged for review. Set this to "always allowed" for the people
                        who genuinely need a phone — area managers, drivers, offsite crews.
                    </p>
                </div>

                {{-- Sits next to the BYOD exception because the two go together in
                     practice: the people who need their own phone are usually the
                     same people who are nowhere near the outlet when they use it.
                     They are still separate switches — one is about the DEVICE,
                     the other about the PLACE — and somebody can need either
                     without the other. --}}
                <div class="sm:w-1/2 sm:pr-1.5">
                    <label class="text-xs font-semibold text-gray-600">Clock in from anywhere</label>
                    <label class="mt-1 flex items-start gap-2 rounded-lg border border-gray-300 px-3 py-2">
                        <input type="checkbox" wire:model="f_allow_anywhere"
                               class="mt-0.5 rounded border-gray-300 text-brand-600">
                        <span class="text-sm text-gray-800">Ignore the outlet's geofence</span>
                    </label>
                    <x-input-error :messages="$errors->get('f_allow_anywhere')" class="mt-1" />
                    {{-- Says plainly what is kept as well as what is dropped, so
                         nobody reads this as "stops recording where they are". --}}
                    <p class="mt-1 text-[11px] text-gray-500">
                        For staff whose work is not at the outlet — area managers touring branches,
                        drivers, offsite crews. Their punches are neither refused nor flagged for being
                        away, and they are not asked to type a reason every time.
                        Where they clocked in is still recorded and still visible on the punch.
                    </p>
                </div>

                <div class="sm:w-1/2 sm:pr-1.5">
                    <label class="text-xs font-semibold text-gray-600">Overtime settled as</label>
                    <label class="mt-1 flex items-start gap-2 rounded-lg border border-gray-300 px-3 py-2">
                        <input type="checkbox" wire:model="f_overtime_as_time_off"
                               class="mt-0.5 rounded border-gray-300 text-brand-600">
                        <span class="text-sm text-gray-800">Time off, not payroll</span>
                    </label>
                    <x-input-error :messages="$errors->get('f_overtime_as_time_off')" class="mt-1" />
                    {{-- A default, not a lock — said plainly, because "all their OT
                         is time off" and "OT defaults to time off" behave the same
                         right up until an approver presses the other button. --}}
                    <p class="mt-1 text-[11px] text-gray-500">
                        Approved overtime for this person goes to their time-off balance instead of
                        their payslip. It is the default the approval screen and bulk approve both use;
                        an approver can still settle a single claim to payroll. Claims already approved
                        keep whichever way they were approved.
                    </p>
                </div>

                <div class="sm:w-1/2 sm:pr-1.5">
                    <label class="text-xs font-semibold text-gray-600">Service charge paid from</label>
                    {{-- $serviceChargeOutlets, not $outlets: it carries whatever
                         this record already points at, even an outlet since closed
                         or one outside this user's access. Listing only reachable
                         outlets rendered an unreachable value as "Their own outlet"
                         and then refused every save. --}}
                    <select wire:model="f_service_charge_outlet_id"
                            class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        <option value="">Their own outlet</option>
                        @foreach ($serviceChargeOutlets as $o)
                            {{-- The label is built here rather than with an @if
                                 inside the option: Livewire wraps every
                                 conditional in HTML comment markers, and inside
                                 an <option> those land in the middle of the
                                 text the user reads. --}}
                            <option value="{{ $o->id }}">{{ $o->trashed() ? $o->name . ' (removed)' : $o->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('f_service_charge_outlet_id')" class="mt-1" />
                    {{-- The consequence is stated because it is not obvious and it
                         is other people's money: this does not top somebody up
                         from elsewhere, it MOVES them, so both pools re-price. --}}
                    <p class="mt-1 text-[11px] text-gray-500">
                        Pays this person from another outlet's service charge pool — for someone posted
                        to one branch but earning alongside another. They join that pool and leave their
                        own, so RM per point changes for <strong>both</strong> outlets.
                        Attendance, roster and clock-in are unaffected.
                    </p>
                </div>
                </div>

                <div class="pt-3 border-t border-gray-100"></div>

                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Compensation</h3>
                        <p class="text-xs text-gray-500">What they are paid and where it goes.</p>
                    </div>
                    <span class="badge-warning whitespace-nowrap">restricted</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        {{-- One column, two meanings, and the difference is who
                             receives the money. For own staff it is what the
                             person is paid; for an agency head it is what the
                             AGENT invoices for them. Calling both "Basic Salary"
                             invites somebody to read a contract rate as take-home
                             pay and then ask why no EPF came off it. --}}
                        <label class="text-xs font-semibold text-gray-600">
                            {{ $isOutsourced ? 'Agent Contract Rate' : 'Basic Salary' }}
                        </label>
                        <input type="number" step="0.01" min="0" wire:model.live="f_basic_salary"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 2500.00" />
                        @if ($isOutsourced)
                            <p class="mt-1 text-[11px] text-gray-600">
                                The rate this company pays the <strong>outsourcing agent</strong> for this head —
                                not the person's take-home pay, which is a matter between them and the agent.
                                It is paid on the agent's invoice, so the bank details on the Personal tab are
                                <strong>not</strong> the payment route for this person.
                            </p>
                        @elseif ($isIntern)
                            {{-- Said where the figure is entered, because it is
                                 the figure: for an intern this is the whole of
                                 what is paid before overtime and allowances,
                                 with nothing coming off it. --}}
                            <p class="mt-1 text-[11px] text-gray-600">
                                The intern's stipend. They are paid this <strong>plus overtime and any
                                allowance</strong>, with nothing deducted — no statutory contributions and
                                no company deductions. Paid directly to them, so the bank details on the
                                Personal tab do apply.
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('f_basic_salary')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Pay Type</label>
                        <select wire:model="f_pay_type" @disabled($f_basic_salary === '')
                                class="mt-1 w-full text-sm rounded-lg border-gray-300 disabled:bg-gray-100 disabled:text-gray-600">
                            @foreach (\App\Models\Employee::PAY_TYPES as $ptValue => $ptLabel)
                                <option value="{{ $ptValue }}">{{ $ptLabel }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('f_pay_type')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Service Points Entitlement</label>
                        <input type="number" step="0.01" min="0" wire:model="f_service_points"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 1.50" />
                        <x-input-error :messages="$errors->get('f_service_points')" class="mt-1" />
                    </div>
                </div>

                {{-- Allowances and salary revisions are DATED and go through an
                     approval, so they stay on their own screen rather than
                     becoming a second door onto the same records. --}}
                @if ($employeeId)
                    <div class="pt-3 border-t border-gray-100">
                        <a href="{{ route('hr.compensation.employee', $employeeId) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800">
                            Allowances, deductions &amp; salary history
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <p class="mt-0.5 text-[11px] text-gray-600">
                            Dated allowances and deductions, and salary changes that need signing off.
                        </p>
                    </div>
                @else
                    <p class="pt-3 border-t border-gray-100 text-[11px] text-gray-600">
                        Allowances and salary history become available once this employee is saved.
                    </p>
                @endif
            </div>

            {{-- ── Statutory ───────────────────────────────────────────── --}}
            <div x-show="tab === 'statutory'" x-cloak class="card p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Statutory</h3>
                        <p class="text-xs text-gray-500">
                            Scheme numbers and the inputs no company-wide setting can answer. Rates live on
                            <a href="{{ route('settings.statutory') }}" class="text-brand-600 hover:underline">Settings → Statutory Rates</a>.
                        </p>
                    </div>
                    <span class="badge-warning whitespace-nowrap">restricted</span>
                </div>

                @if ($noStatutory)
                    {{-- Disabled, not hidden, and the difference matters.

                         Hiding it would leave whoever opens this tab wondering
                         whether the section failed to load; worse, a person
                         moved off the agency — or an intern taken onto the
                         books — needs their EPF number to still BE here rather
                         than to have been quietly discarded. So the values are
                         kept and the controls are shut: nothing is written
                         while the status applies, and changing it back hands
                         the whole section over intact.

                         The calculation does not rely on this being disabled —
                         StatutoryCalculator::for() exempts these employees
                         outright, whatever these boxes say. This is the same
                         rule shown, not the rule itself. --}}
                    <div class="alert-info">
                        <p class="text-sm">
                            <strong>
                                Not applicable — this employee is
                                {{ $isIntern ? 'on an internship' : 'outsourced' }}.
                            </strong>
                        </p>
                        @if ($isIntern)
                            <p class="text-xs mt-1">
                                An internship is paid as basic plus overtime and any allowance, with
                                <strong>nothing deducted</strong> — no EPF, SOCSO, EIS, PCB or HRD Corp levy,
                                and no company deductions either. Payroll runs these figures at zero and
                                payslips say so.
                            </p>
                        @else
                            <p class="text-xs mt-1">
                                EPF, SOCSO, EIS, PCB and the HRD Corp levy are the obligation of
                                <strong>{{ $f_outsourcing_provider === 'others' ? ($f_outsourcing_company ?: 'the outsourcing agent') : 'Experiva' }}</strong>
                                as the employer of record. This company pays a contract rate for the head and
                                deducts nothing, so payroll runs these figures at zero and payslips say so.
                            </p>
                        @endif
                        <p class="text-xs mt-1">
                            Anything already recorded below is kept and shown, but nothing here is used
                            while this status applies. Change their employment status on the
                            <strong>Employment</strong> tab to bring this section back.
                        </p>
                    </div>
                @endif

                {{-- fieldset, so one `disabled` shuts every control inside it
                     rather than thirteen @disabled attributes that can drift
                     apart as fields are added. --}}
                <fieldset @disabled($noStatutory) class="space-y-4 disabled:opacity-60">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">EPF No.</label>
                        <input type="text" maxlength="30" wire:model="s_epf_number" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('s_epf_number')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">SOCSO No.</label>
                        <input type="text" maxlength="30" wire:model="s_socso_number" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('s_socso_number')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Income Tax No.</label>
                        <input type="text" maxlength="30" wire:model="s_tax_number" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('s_tax_number')" class="mt-1" />
                    </div>
                </div>

                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="s_is_malaysian" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">Malaysian citizen / PR</span>
                </label>

                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-3">
                    <p class="text-xs font-semibold text-gray-600">Contributes to</p>
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="s_epf" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="text-sm text-gray-700">EPF</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="s_socso" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="text-sm text-gray-700">SOCSO</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="s_eis" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="text-sm text-gray-700">EIS</span></label>
                        <label class="inline-flex items-center gap-2" title="HRD Corp levy — paid by the employer, never deducted from this employee"><input type="checkbox" wire:model="s_hrdf" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="text-sm text-gray-700">HRDF</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="s_pcb" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="text-sm text-gray-700">PCB</span></label>
                    </div>

                    {{-- SKBBK is a SELECT, not a tick, because it has three
                         answers and only two of them are decisions.

                         Mandatory for foreign workers; voluntary for locals
                         since 14 July 2026, when they could opt out by filing
                         a liability release. "Not recorded" and "opted out"
                         look identical on a payslip — both deduct nothing —
                         but only one has a signed release behind it, and a
                         tickbox would collapse them into each other. --}}
                    <div class="sm:w-2/3 pt-1">
                        <label class="text-xs font-semibold text-gray-600">
                            SKBBK — LINDUNG 24 Jam
                        </label>
                        <select wire:model="s_skbbk" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                            <option value="">Follow the rule — {{ $s_is_malaysian ? 'not deducted (local, voluntary)' : 'deducted (foreign worker, mandatory)' }}</option>
                            <option value="yes">Yes — contributes</option>
                            <option value="no">No — opted out, release on file</option>
                        </select>
                        <p class="mt-1 text-[11px] text-gray-500">
                            Paid entirely by the employee; there is no employer share. Mandatory for
                            foreign workers. Voluntary for locals since 14 July 2026 — set
                            <strong>Yes</strong> for a local who chose to stay in.
                        </p>
                    </div>
                    <div class="sm:w-1/2">
                        <label class="text-xs font-semibold text-gray-600">EPF employee rate override (%)</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model="s_epf_override"
                               placeholder="statutory rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <p class="mt-1 text-[11px] text-gray-500">For a voluntary higher rate. It never lowers the statutory one.</p>
                        <x-input-error :messages="$errors->get('s_epf_override')" class="mt-1" />
                    </div>
                </div>

                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-3">
                    <p class="text-xs font-semibold text-gray-600">PCB inputs</p>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Category</label>
                            <select wire:model="s_pcb_category" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                @foreach (\App\Models\StatutorySetting::PCB_CATEGORIES as $pcValue => $pcLabel)
                                    <option value="{{ $pcValue }}">{{ $pcLabel }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('s_pcb_category')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Children</label>
                            <input type="number" min="0" max="50" wire:model="s_children" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('s_children')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Monthly zakat</label>
                            <input type="number" step="0.01" min="0" wire:model="s_zakat" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('s_zakat')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Other relief / yr</label>
                            <input type="number" step="0.01" min="0" wire:model="s_other_relief" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('s_other_relief')" class="mt-1" />
                        </div>
                    </div>
                </div>

                </fieldset>
            </div>
        @endif

        {{-- ── Certifications ──────────────────────────────────────────── --}}
        <div x-show="tab === 'compliance'" x-cloak class="card p-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Certifications &amp; Training</h3>
                <p class="text-xs text-gray-500">What they hold, and what has to be renewed.</p>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="f_food_handler_certified" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Food Handler Certified</span>
            </label>
            @if ($f_food_handler_certified)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Food Handler Certificate — Serial No.</label>
                        <input type="text" wire:model="f_food_handler_cert_no" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. FHC-2026-0123" />
                        <x-input-error :messages="$errors->get('f_food_handler_cert_no')" class="mt-1" />
                    </div>
                    @if ($complianceSettings->expires('food_handler'))
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Food Handler — Expires On</label>
                            <input type="date" wire:model="f_food_handler_expired_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('f_food_handler_expired_on')" class="mt-1" />
                        </div>
                    @endif
                </div>
            @endif

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="f_typhoid_card" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Typhoid Card (jab taken)</span>
            </label>
            @if ($f_typhoid_card)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Typhoid Card — Valid From</label>
                        <input type="date" wire:model="f_typhoid_valid_from" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_typhoid_valid_from')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Typhoid Card — Expired On</label>
                        <input type="date" wire:model="f_typhoid_expired_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_typhoid_expired_on')" class="mt-1" />
                    </div>
                </div>
            @endif

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="f_halal_training" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Halal Awareness Training</span>
            </label>
            @if ($f_halal_training)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Halal Awareness Training — Date Attended</label>
                        <input type="date" wire:model="f_halal_training_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_halal_training_date')" class="mt-1" />
                    </div>
                    @if ($complianceSettings->expires('halal'))
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Halal Training — Expires On</label>
                            <input type="date" wire:model="f_halal_training_expired_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('f_halal_training_expired_on')" class="mt-1" />
                        </div>
                    @endif
                </div>
            @endif

            {{-- Company catalogue courses. Managed under Settings → Certifications & Training. --}}
            <div class="pt-3 mt-1 border-t border-gray-100">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-600">Other Certifications &amp; Training</p>
                        <p class="text-[11px] text-gray-500">Courses your company tracks, from Settings → Certifications &amp; Training.</p>
                    </div>
                    <button type="button" wire:click="addCertification"
                            @disabled($availableCertifications->isEmpty())
                            class="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed">
                        + Add
                    </button>
                </div>

                <x-input-error :messages="$errors->get('f_certifications')" class="mt-2" />

                @forelse ($f_certifications as $i => $cert)
                    <div wire:key="cert-row-{{ $i }}" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <div class="sm:col-span-4">
                                <label class="text-xs font-semibold text-gray-600">Course <span class="text-danger-500">*</span></label>
                                <select wire:model="f_certifications.{{ $i }}.type_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">— Select —</option>
                                    {{-- The row's own course stays listed even though it is taken,
                                         or editing another field would silently blank this one. --}}
                                    @if (! empty($cert['type_id']) && $certificationTypes->has((int) $cert['type_id']))
                                        <option value="{{ $cert['type_id'] }}">{{ $certificationTypes[(int) $cert['type_id']]->name }}</option>
                                    @endif
                                    @foreach ($availableCertifications as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('f_certifications.' . $i . '.type_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-3">
                                <label class="text-xs font-semibold text-gray-600">Certificate No.</label>
                                <input type="text" wire:model="f_certifications.{{ $i }}.reference_no" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('f_certifications.' . $i . '.reference_no')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-semibold text-gray-600">Issued</label>
                                <input type="date" wire:model="f_certifications.{{ $i }}.issued_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-semibold text-gray-600">Expires</label>
                                <input type="date" wire:model="f_certifications.{{ $i }}.expires_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            </div>
                            <div class="sm:col-span-1 flex items-end pb-1">
                                <button type="button" wire:click="removeCertification({{ $i }})"
                                        title="Remove" class="icon-btn text-danger-400 hover:text-danger-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </div>
                        @if (! empty($cert['type_id']) && $certificationTypes->has((int) $cert['type_id']) && ! $certificationTypes[(int) $cert['type_id']]->has_expiry)
                            <p class="mt-2 text-[11px] text-gray-500">This is a one-off course — it will not be chased for renewal even without an expiry date.</p>
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-xs text-gray-500">
                        @if ($availableCertifications->isEmpty())
                            No courses in the catalogue yet — add them under
                            <a href="{{ route('settings.certifications') }}" class="text-brand-600 hover:underline">Settings → Certifications &amp; Training</a>.
                        @else
                            None recorded.
                        @endif
                    </p>
                @endforelse
            </div>
        </div>

        {{-- ── Documents (edit only) ───────────────────────────────────── --}}
        @if ($employeeId)
            @php
                // The two document types whose expiry the record already
                // tracks. Shown here so the scan and the date that depends on
                // it are never read apart.
                $expiryByType = [
                    'typhoid_card'      => $f_typhoid_card ? $f_typhoid_expired_on : '',
                    'food_handler_cert' => $f_food_handler_certified ? $f_food_handler_expired_on : '',
                ];
            @endphp
            <div x-show="tab === 'documents'" x-cloak class="space-y-4">
                <div class="card p-5 space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Documents</h3>
                        <p class="text-xs text-gray-500">
                            Scanned copies held against this employee. Stored privately — only staff who can
                            open this record can open the files.
                        </p>
                    </div>

                    @if (session('doc_success'))
                        <div class="alert-success">{{ session('doc_success') }}</div>
                    @endif
                    @if (session('doc_error'))
                        <div class="alert-danger">{{ session('doc_error') }}</div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-start">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Type</label>
                            <select wire:model="docType" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                @foreach (\App\Models\EmployeeDocument::TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Label</label>
                            <input type="text" maxlength="120" wire:model="docLabel"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="Optional" />
                            <x-input-error :messages="$errors->get('docLabel')" class="mt-1" />
                        </div>
                        {{-- NO SEPARATE UPLOAD BUTTON. Choosing the file saves
                             it — see EmployeeForm::updatedDocFile for the
                             server-side evidence that the button was losing
                             people's paperwork: five files chosen in five
                             minutes, not one document row written, because
                             Livewire uploads on CHOOSE and the button beside it
                             is disabled while that upload is in flight. --}}
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-gray-600">File</label>
                            <input type="file" wire:model="docFile" accept=".pdf,image/*"
                                   class="mt-1 w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-control file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                PDF or photo, up to 10 MB. Set the type and label first — the file saves
                                as soon as you choose it.
                            </p>
                            <div wire:loading wire:target="docFile" class="mt-1 text-[11px] font-medium text-brand-700">
                                Saving…
                            </div>
                            <x-input-error :messages="$errors->get('docFile')" class="mt-1" />
                            <x-input-error :messages="$errors->get('docType')" class="mt-1" />
                        </div>
                    </div>
                </div>

                @foreach (\App\Models\EmployeeDocument::TYPES as $value => $label)
                    @php $held = $documents[$value] ?? collect(); @endphp
                    <div class="card p-4" wire:key="doctype-{{ $value }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700">{{ $label }}</h4>
                                @if (($expiryByType[$value] ?? '') !== '')
                                    <p class="text-[11px] text-gray-600">
                                        Recorded as expiring {{ \Carbon\Carbon::parse($expiryByType[$value])->format('j M Y') }}
                                        — set on the Certifications tab.
                                    </p>
                                @endif
                            </div>
                            @if ($held->isEmpty())
                                <span class="badge-neutral">None on file</span>
                            @else
                                <span class="badge-success">{{ $held->count() }} on file</span>
                            @endif
                        </div>

                        @if ($held->isNotEmpty())
                            <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                                @foreach ($held as $doc)
                                    <li class="flex flex-wrap items-center gap-3 py-2" wire:key="doc-{{ $doc->id }}">
                                        <x-icon name="document" class="h-4 w-4 text-gray-400 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-gray-800 truncate">
                                                {{ $doc->label ?: $doc->original_name }}
                                            </p>
                                            <p class="text-[11px] text-gray-500">
                                                {{ $doc->sizeLabel() }} &middot; uploaded {{ $doc->created_at->format('j M Y') }}
                                                @if ($doc->uploader) by {{ $doc->uploader->name }} @endif
                                            </p>
                                        </div>
                                        @canDo('hr.view')
                                        <a href="{{ route('hr.employee-documents.show', $doc) }}" target="_blank" rel="noopener"
                                           class="text-xs font-medium text-brand-600 hover:text-brand-800">View</a>
                                        <a href="{{ route('hr.employee-documents.download', $doc) }}"
                                           class="text-xs font-medium text-gray-600 hover:text-gray-900">Download</a>
                                        @endcanDo
                                        {{-- Behind its own ability. Deleting takes
                                             the file off disk with no undo, so it
                                             is not something "can edit staff"
                                             should carry — see
                                             hr.employees.documents.delete. The
                                             server checks it too; this only stops
                                             the accident. --}}
                                        @canDo('hr.employees.documents.delete')
                                        <button type="button" wire:click="deleteDocument({{ $doc->id }})"
                                                data-confirm-delete="Delete this document? The file is removed for good."
                                                class="text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                        @endcanDo
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ── Activity (edit only) ────────────────────────────────────── --}}
        @if ($employeeId)
            <div x-show="tab === 'activity'" x-cloak class="card p-5">
                <x-audit-timeline :type="\App\Models\Employee::class" :id="$employeeId" title="Employee Activity" />
            </div>
        @endif

        <div class="flex items-center justify-end gap-2">
            @canDo('hr.view')
            <a href="{{ route('hr.employees', $this->returnParams()) }}" class="btn-secondary">Cancel</a>
            @endcanDo
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</div>
