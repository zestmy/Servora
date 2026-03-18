<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Back + Header --}}
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Labour Costs</p>
        </div>
    </div>

    <p class="text-xs text-gray-400 mb-4">Enter monthly labour cost totals per outlet, split by Front of House (FOH) and Back of House (BOH). Used for labour cost vs sales reporting.</p>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Month nav --}}
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <input type="month" wire:model.live="period"
                       class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <button wire:click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Outlet --}}
            <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— Select Outlet —</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (!$outletId)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
            <p class="font-medium">Select an outlet to manage labour costs</p>
        </div>
    @else
        {{-- FOH & BOH Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach (['foh' => 'Front of House (FOH)', 'boh' => 'Back of House (BOH)'] as $type => $label)
                @php
                    $rec = $records[$type] ?? null;
                    $totalAllowances = $rec ? (float) $rec->allowances->sum('amount') : 0;
                    $totalCost = $rec ? ((float) $rec->basic_salary + (float) $rec->service_point + (float) $rec->epf + (float) $rec->eis + (float) $rec->socso + $totalAllowances) : 0;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 {{ $type === 'foh' ? 'bg-blue-50' : 'bg-amber-50' }}">
                        <h3 class="text-sm font-semibold {{ $type === 'foh' ? 'text-blue-800' : 'text-amber-800' }}">{{ $label }}</h3>
                        <button wire:click="openEdit('{{ $type }}')"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg transition {{ $rec ? 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                            {{ $rec ? 'Edit' : '+ Enter' }}
                        </button>
                    </div>

                    @if ($rec)
                        <div class="px-5 py-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Basic Salary</span>
                                <span class="font-medium text-gray-800">{{ number_format((float) $rec->basic_salary, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Service Point</span>
                                <span class="font-medium text-gray-800">{{ number_format((float) $rec->service_point, 2) }}</span>
                            </div>
                            @foreach ($rec->allowances as $allowance)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $allowance->label }}</span>
                                    <span class="font-medium text-gray-800">{{ number_format((float) $allowance->amount, 2) }}</span>
                                </div>
                            @endforeach
                            <div class="flex justify-between">
                                <span class="text-gray-500">EPF</span>
                                <span class="font-medium text-gray-800">{{ number_format((float) $rec->epf, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">EIS</span>
                                <span class="font-medium text-gray-800">{{ number_format((float) $rec->eis, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">SOCSO</span>
                                <span class="font-medium text-gray-800">{{ number_format((float) $rec->socso, 2) }}</span>
                            </div>
                            <div class="border-t border-gray-100 pt-2 mt-2 flex justify-between">
                                <span class="font-semibold text-gray-700">Total Labour Cost</span>
                                <span class="font-bold {{ $type === 'foh' ? 'text-blue-700' : 'text-amber-700' }}">{{ number_format($totalCost, 2) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="px-5 py-8 text-center text-gray-400 text-sm">
                            <p>No data entered for {{ $periodLabel }}</p>
                            <p class="text-xs mt-1">Click "+ Enter" to add labour costs</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Combined Summary --}}
        @php
            $foh = $records['foh'] ?? null;
            $boh = $records['boh'] ?? null;
            $fohTotal = $foh ? $foh->total_cost : 0;
            $bohTotal = $boh ? $boh->total_cost : 0;
            $grandTotal = $fohTotal + $bohTotal;
        @endphp
        @if ($foh || $boh)
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ $periodLabel }} — Total Labour Cost</h3>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">FOH</p>
                        <p class="text-lg font-bold text-blue-700">{{ number_format($fohTotal, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">BOH</p>
                        <p class="text-lg font-bold text-amber-700">{{ number_format($bohTotal, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Total</p>
                        <p class="text-lg font-bold text-gray-800">{{ number_format($grandTotal, 2) }}</p>
                    </div>
                </div>
                @if ($grandTotal > 0)
                    <div class="mt-3 flex rounded-full overflow-hidden h-2.5">
                        <div class="bg-blue-500" style="width: {{ round($fohTotal / $grandTotal * 100) }}%"></div>
                        <div class="bg-amber-500" style="width: {{ round($bohTotal / $grandTotal * 100) }}%"></div>
                    </div>
                    <div class="flex justify-between mt-1 text-xs text-gray-400">
                        <span>FOH {{ $grandTotal > 0 ? round($fohTotal / $grandTotal * 100, 1) : 0 }}%</span>
                        <span>BOH {{ $grandTotal > 0 ? round($bohTotal / $grandTotal * 100, 1) : 0 }}%</span>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editDeptType === 'foh' ? 'Front of House (FOH)' : 'Back of House (BOH)' }} — {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                    {{-- Basic Salary --}}
                    <div>
                        <x-input-label for="lc_basic" value="Basic Salary *" />
                        <x-text-input id="lc_basic" wire:model="basic_salary" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('basic_salary')" class="mt-1" />
                    </div>

                    {{-- Service Point --}}
                    <div>
                        <x-input-label for="lc_sp" value="Service Point *" />
                        <x-text-input id="lc_sp" wire:model="service_point" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('service_point')" class="mt-1" />
                    </div>

                    {{-- Allowances --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <x-input-label value="Allowances" />
                            <button type="button" wire:click="addAllowance"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Allowance</button>
                        </div>
                        @foreach ($allowances as $i => $row)
                            <div class="flex items-center gap-2 mb-2" wire:key="allowance-{{ $i }}">
                                <input type="text" wire:model="allowances.{{ $i }}.label" placeholder="e.g. Housing"
                                       class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <input type="number" wire:model="allowances.{{ $i }}.amount" step="0.01" min="0"
                                       class="w-32 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <button type="button" wire:click="removeAllowance({{ $i }})"
                                        class="text-red-400 hover:text-red-600 transition flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                        @if (count($allowances) === 0)
                            <p class="text-xs text-gray-400">No custom allowances. Click "+ Add Allowance" to add one.</p>
                        @endif
                    </div>

                    {{-- Divider --}}
                    <div class="border-t border-gray-100"></div>

                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Mandatory Contributions</p>

                    {{-- EPF --}}
                    <div>
                        <x-input-label for="lc_epf" value="EPF *" />
                        <x-text-input id="lc_epf" wire:model="epf" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('epf')" class="mt-1" />
                    </div>

                    {{-- EIS --}}
                    <div>
                        <x-input-label for="lc_eis" value="EIS *" />
                        <x-text-input id="lc_eis" wire:model="eis" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('eis')" class="mt-1" />
                    </div>

                    {{-- SOCSO --}}
                    <div>
                        <x-input-label for="lc_socso" value="SOCSO *" />
                        <x-text-input id="lc_socso" wire:model="socso" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('socso')" class="mt-1" />
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save
                    </button>
                </div>
            </form>

        </div>
        </div>
        </div>
    </div>
    @endteleport
</div>
