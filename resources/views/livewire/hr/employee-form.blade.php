<div>
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('hr.employees') }}" title="Back to Employees"
               class="mt-0.5 p-1.5 rounded-control text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <p class="text-xs text-gray-600">HR / Employees</p>
                <h2 class="text-lg font-semibold text-gray-700 mt-1">
                    {{ $employeeId ? 'Edit Employee' : 'Add Employee' }}
                </h2>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr.employees') }}" class="btn-secondary">Cancel</a>
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

    <form id="employee-form" wire:submit.prevent="save" class="space-y-4">

        {{-- Basics --}}
        <div class="card p-5 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Details</h3>
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
                    <label class="text-xs font-semibold text-gray-600">Staff ID</label>
                    <input type="text" wire:model="f_staff_id" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. EMP-001" />
                    <x-input-error :messages="$errors->get('f_staff_id')" class="mt-1" />
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Employee Name <span class="text-danger-500">*</span></label>
                <input type="text" wire:model="f_name" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Designation</label>
                    <input type="text" wire:model="f_designation" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Kitchen Helper" />
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
                    <label class="text-xs font-semibold text-gray-600">E-mail</label>
                    <input type="email" wire:model="f_email" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
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
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Join Date</label>
                    <input type="date" wire:model="f_join_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('f_join_date')" class="mt-1" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Employment --}}
        <div class="card p-5 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Employment</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        <input type="date" wire:model="f_employment_status_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
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

        {{-- Compensation — restricted to hr.compensation holders. --}}
        @if ($canViewPay)
            <div class="card p-5">
                <div class="p-3 bg-warning-50/60 rounded-lg border border-warning-100 space-y-3">
                    <p class="text-[11px] font-semibold text-warning-700 uppercase tracking-wide flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Compensation — restricted
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-gray-600">Basic Salary</label>
                            <input type="number" step="0.01" min="0" wire:model.live="f_basic_salary"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 2500.00" />
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Service Points Entitlement</label>
                            <input type="number" step="0.01" min="0" wire:model="f_service_points"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 1.50" />
                            <x-input-error :messages="$errors->get('f_service_points')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Certifications --}}
        <div class="card p-5 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Certifications &amp; Training</h3>

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

        {{-- Recent activity (edit only) --}}
        @if ($employeeId)
            <div class="card p-5">
                <x-audit-timeline :type="\App\Models\Employee::class" :id="$employeeId" title="Employee Activity" />
            </div>
        @endif

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('hr.employees') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</div>
