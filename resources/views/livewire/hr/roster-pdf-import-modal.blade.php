<div x-data="{ open: @entangle('open') }">
<template x-teleport="body">
<div x-show="open" x-cloak
     @keydown.escape.window="if (open) $wire.close()"
     class="fixed inset-0 z-[100] overflow-y-auto">
    <div class="fixed inset-0 bg-black/50" wire:click="close"></div>
    <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
        <div class="relative bg-white rounded-panel shadow-e4 w-full {{ $parsed ? 'max-w-7xl' : 'max-w-xl' }}" @click.stop>

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-100">
                <div>
                    <p class="page-eyebrow">Duty Roster</p>
                    <h3 class="text-lg font-semibold text-gray-900">
                        @if ($parsed)
                            Check and import
                        @else
                            Import roster from PDF
                        @endif
                    </h3>
                    @if ($parsed)
                        <p class="help mt-0.5">
                            Week of <span class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($parsed['week_start'])->format('D j M') }} – {{ \Carbon\Carbon::parse($parsed['week_end'])->format('D j M Y') }}</span>
                            · read from <span class="font-medium text-gray-800">{{ $fileName }}</span>
                        </p>
                    @endif
                </div>
                <button type="button" wire:click="close" class="icon-btn" aria-label="Close">
                    <x-icon name="close" class="h-5 w-5" />
                </button>
            </div>

            @if ($parsed && ! $review)
                <div class="p-5"><div class="alert-warning"><x-icon name="warning" class="h-5 w-5 shrink-0" /><div>Select an outlet on the Duty Roster first.</div></div></div>
            @elseif (! $parsed)
                {{-- Step 1: drop the file --}}
                <div class="p-5 space-y-4">
                    <label x-data="{ over: false }"
                           @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop="over = false"
                           :class="over ? 'border-brand-500 bg-brand-50' : 'border-gray-300 hover:border-brand-400 hover:bg-gray-50'"
                           class="relative flex flex-col items-center justify-center gap-3 rounded-surface border-2 border-dashed px-6 py-12 text-center cursor-pointer transition">
                        <input type="file" wire:model="pdf" accept="application/pdf,.pdf"
                               class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />

                        <div wire:loading.remove wire:target="pdf" class="flex flex-col items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <x-icon name="document" class="h-6 w-6" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Drop your roster PDF here, or click to choose</p>
                                <p class="help mt-1">The weekly sheet from Excel — File → Save as PDF. Up to 10 MB.</p>
                            </div>
                        </div>

                        <div wire:loading.flex wire:target="pdf" class="flex-col items-center gap-3">
                            <svg class="h-8 w-8 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <p class="text-sm font-medium text-gray-700">Reading the roster…</p>
                        </div>
                    </label>

                    @error('pdf')
                        <div class="alert-danger"><x-icon name="alert" class="h-5 w-5 shrink-0" /><div>{{ $message }}</div></div>
                    @enderror
                    @if ($error)
                        <div class="alert-danger"><x-icon name="alert" class="h-5 w-5 shrink-0" /><div>{{ $error }}</div></div>
                    @endif

                    <div class="rounded-surface bg-gray-50 p-4 text-sm text-gray-700">
                        <p class="font-medium text-gray-900 mb-2">What gets read</p>
                        <ul class="space-y-1 list-disc pl-5">
                            <li>The week from the date row (e.g. 28-Sep … 4-Oct).</li>
                            <li>Each person's shift per day — <span class="font-mono text-xs">7AM-3.30PM</span>, <span class="font-mono text-xs">OFF DAY</span>, <span class="font-mono text-xs">AL</span>, <span class="font-mono text-xs">MC</span>, <span class="font-mono text-xs">CLAIM HOUR</span>.</li>
                            <li>The station written under a shift (<span class="font-mono text-xs">BAR AM</span>, <span class="font-mono text-xs">MOD PM</span>).</li>
                            <li>Public holiday and event rows as day remarks.</li>
                        </ul>
                        <p class="help mt-2">You'll see everything before anything is saved.</p>
                    </div>
                </div>
            @else
                {{-- Step 2: review --}}
                <div class="p-5 space-y-4">
                    {{-- Target + summary --}}
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="label label-req">Section</label>
                            <select wire:model.live="sectionId" class="input mt-1 min-w-[160px]">
                                <option value="">Choose…</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-wrap gap-2 pb-1">
                            <span class="badge-brand">{{ $review['staff'] }} staff</span>
                            <span class="badge-neutral">{{ $review['shifts'] }} shifts</span>
                            <span class="badge-neutral">{{ $review['leave'] }} off / leave</span>
                            @if ($review['notes'])
                                <span class="badge-warning" title="Cells with text but no times — they import as a working day with a note">{{ $review['notes'] }} without times</span>
                            @endif
                            @if (count($parsed['remarks']))
                                <span class="badge-info">{{ count($parsed['remarks']) }} day remark(s)</span>
                            @endif
                            @if ($review['unassigned'])
                                <span class="badge-warning">{{ $review['unassigned'] }} row(s) not matched</span>
                            @endif
                        </div>
                        <div class="flex-1"></div>
                        @if ($parsed['title'])
                            <p class="help max-w-sm truncate pb-1" title="{{ $parsed['title'] }}">{{ $parsed['title'] }}</p>
                        @endif
                    </div>

                    @if ($review['roster'] && $review['roster']->isDraft())
                        <div class="alert-info">
                            <x-icon name="info" class="h-5 w-5 shrink-0" />
                            <div>
                                A draft roster already exists for this week.
                                @if ($review['replacing'])
                                    The {{ $review['replacing'] }} existing entr{{ $review['replacing'] === 1 ? 'y' : 'ies' }} for the staff below will be replaced by the sheet;
                                @endif
                                anyone on the draft who isn't on the sheet is left as they are.
                            </div>
                        </div>
                    @endif

                    {{-- The week as it will be imported --}}
                    <div class="table-surface overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-left text-gray-600">
                                    <th class="px-3 py-2 font-medium">On the sheet</th>
                                    <th class="px-3 py-2 font-medium min-w-[220px]">Employee</th>
                                    @foreach ($parsed['dates'] as $date)
                                        @php $d = \Carbon\Carbon::parse($date); @endphp
                                        <th class="px-2 py-2 font-medium text-center min-w-[92px]">
                                            {{ $d->format('D') }} <span class="text-gray-500 font-normal">{{ $d->format('j') }}</span>
                                            @if (isset($parsed['remarks'][$date]))
                                                <div class="mt-0.5 truncate max-w-[110px] mx-auto font-normal text-info-700" title="{{ $parsed['remarks'][$date]['text'] }}">
                                                    {{ $parsed['remarks'][$date]['text'] }}
                                                </div>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($parsed['rows'] as $i => $row)
                                    @php
                                        $picked = $assign[$i] ?? '';
                                        $status = $how[$i] ?? 'none';
                                        $isDup = $picked !== '' && in_array((int) $picked, $review['duplicates'], true);
                                        $needsAttention = $picked === '' && $status !== 'skipped';
                                    @endphp
                                    <tr wire:key="import-row-{{ $i }}"
                                        class="{{ $needsAttention || $isDup ? 'bg-warning-50' : ($picked === '' ? 'opacity-50' : '') }}">
                                        <td class="px-3 py-2 align-top">
                                            <div class="font-semibold text-gray-900">{{ $row['name'] }}</div>
                                            @if ($row['position'])
                                                <div class="text-gray-500">{{ $row['position'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <select wire:model.live="assign.{{ $i }}"
                                                    class="input py-1.5 text-xs w-full {{ $needsAttention || $isDup ? 'border-warning-400' : '' }}">
                                                <option value="">— Don't import this row —</option>
                                                @foreach ($employees as $emp)
                                                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mt-1">
                                                @if ($isDup)
                                                    <span class="text-warning-800">Also picked on another row</span>
                                                @elseif ($status === 'remembered')
                                                    <span class="text-success-700">✓ Remembered from last import</span>
                                                @elseif ($status === 'name')
                                                    <span class="text-success-700">✓ Matched by name</span>
                                                @elseif ($status === 'picked')
                                                    <span class="text-gray-600">Picked — remembered next time</span>
                                                @elseif ($status === 'skipped')
                                                    <span class="text-gray-600">Skipped</span>
                                                @elseif ($status === 'ambiguous')
                                                    <span class="text-warning-800">Several staff match — pick one</span>
                                                @else
                                                    <span class="text-warning-800">No match — pick the employee</span>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach ($parsed['dates'] as $date)
                                            @php $cell = $row['cells'][$date]; @endphp
                                            <td class="px-1.5 py-2 align-top text-center">
                                                @if ($cell['kind'] === 'shift')
                                                    @php
                                                        $s = \Carbon\Carbon::createFromFormat('H:i', $cell['start']);
                                                        $e = \Carbon\Carbon::createFromFormat('H:i', $cell['end']);
                                                        $label = $s->format($s->minute ? 'g:iA' : 'gA') . '–' . $e->format($e->minute ? 'g:iA' : 'gA');
                                                        $tone = $s->hour < 10 ? 'bg-emerald-50 text-emerald-800' : ($s->hour < 14 ? 'bg-sky-50 text-sky-800' : 'bg-violet-50 text-violet-800');
                                                        $template = $review['shift_names'][$cell['start'] . '-' . $cell['end']] ?? null;
                                                    @endphp
                                                    <div class="rounded-control px-1 py-1 {{ $tone }}" title="{{ $cell['raw'] }}{{ $template ? ' — shift: ' . $template : '' }}">
                                                        <div class="font-medium whitespace-nowrap">{{ $label }}</div>
                                                        @if ($cell['station'])
                                                            <div class="text-[10px] opacity-80 leading-tight">{{ $cell['station'] }}</div>
                                                        @endif
                                                    </div>
                                                @elseif ($cell['kind'] === 'leave')
                                                    <div class="rounded-control px-1 py-1 font-medium {{ $cell['leave'] === 'off' ? 'bg-gray-100 text-gray-600' : 'bg-warning-50 text-warning-800' }}" title="{{ $cell['raw'] }}">
                                                        {{ $leaveShort[$cell['leave']] ?? strtoupper($cell['leave']) }}
                                                    </div>
                                                @elseif ($cell['kind'] === 'note')
                                                    <div class="rounded-control border border-dashed border-warning-300 bg-warning-50 px-1 py-1 text-warning-800"
                                                         title="No times on the sheet — imported as a working day with this note. Add the times after importing.">
                                                        <div class="font-medium leading-tight">{{ $cell['raw'] }}</div>
                                                        <div class="text-[10px] leading-tight">no times</div>
                                                    </div>
                                                @else
                                                    <span class="text-gray-500">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Stations the outlet doesn't have yet --}}
                    @if (count($review['unknown_stations']))
                        <div class="rounded-surface border border-gray-200 p-4">
                            <p class="text-sm font-medium text-gray-900">New stations on this sheet</p>
                            <p class="help mt-0.5">Ticked labels are added to this outlet's Stations. Unticked ones are kept as a note on the shift instead.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($review['unknown_stations'] as $label)
                                    <label wire:key="station-{{ md5($label) }}"
                                           class="inline-flex items-center gap-2 rounded-control border border-gray-200 px-3 py-1.5 text-sm cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" wire:model="createStations" value="{{ $label }}"
                                               class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($review['not_on_sheet']->isNotEmpty())
                        <p class="help">
                            <span class="font-medium text-gray-700">Not on this sheet:</span>
                            {{ $review['not_on_sheet']->take(12)->implode(', ') }}{{ $review['not_on_sheet']->count() > 12 ? ' and ' . ($review['not_on_sheet']->count() - 12) . ' more' : '' }}
                            — they won't be added to the roster.
                        </p>
                    @endif

                    @if ($error)
                        <div class="alert-danger"><x-icon name="alert" class="h-5 w-5 shrink-0" /><div>{{ $error }}</div></div>
                    @elseif ($review['blocked'])
                        <div class="alert-warning"><x-icon name="warning" class="h-5 w-5 shrink-0" /><div>{{ $review['blocked'] }}</div></div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50 rounded-b-panel">
                    <button type="button" wire:click="startOver" class="btn-ghost">
                        Use a different file
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="close" class="btn-secondary">Cancel</button>
                        <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import"
                                @disabled($review['blocked'])
                                class="btn-primary">
                            <span wire:loading.remove wire:target="import">
                                Import {{ $review['staff'] }} staff{{ $review['roster'] ? '' : ' as a new draft' }}
                            </span>
                            <span wire:loading wire:target="import">Importing…</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</template>
</div>
