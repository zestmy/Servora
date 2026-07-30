@php
    // Screen scale for the canvas. 4px per mm makes a 70mm label 280px —
    // big enough to judge, small enough to sit beside the controls.
    $px = 4;
    $w  = (float) $width_mm;
    $h  = (float) $height_mm;
@endphp

<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('labels.templates') }}" wire:navigate class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="text-xs text-gray-400">Labels / Templates / Design</p>
                <h2 class="text-lg font-semibold text-gray-700 mt-1">{{ $template->name }}</h2>
            </div>
        </div>
        <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            Save template
        </button>
    </div>

    @if (count($overflow))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            <strong>{{ count($overflow) }} field{{ count($overflow) === 1 ? '' : 's' }} hang off the label.</strong>
            Anything past the edge makes the printer paginate, and every extra page is a wasted label.
            Fix these before saving.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Canvas --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex flex-wrap items-end gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                        <input type="text" wire:model.blur="name" class="rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Width (mm)</label>
                        <input type="number" step="0.5" wire:model.live="width_mm" class="w-24 rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Height (mm)</label>
                        <input type="number" step="0.5" wire:model.live="height_mm" class="w-24 rounded-lg border-gray-300 text-sm">
                    </div>
                </div>

                <p class="text-xs text-gray-400 mb-2">Click a field to select it. Grid lines are 5mm.</p>

                <div class="overflow-x-auto">
                    <div class="relative border border-gray-800 bg-white"
                         style="width: {{ $w * $px }}px; height: {{ $h * $px }}px;
                                background-image:
                                    repeating-linear-gradient(to right, #f1f5f9 0 1px, transparent 1px {{ 5 * $px }}px),
                                    repeating-linear-gradient(to bottom, #f1f5f9 0 1px, transparent 1px {{ 5 * $px }}px);">
                        @foreach ($fields as $i => $f)
                            @php $bad = isset($overflow[$i]); @endphp
                            <button type="button" wire:click="selectField({{ $i }})"
                                    wire:key="canvas-{{ $i }}"
                                    class="absolute text-left overflow-hidden {{ $selected === $i ? 'ring-2 ring-indigo-500 bg-indigo-50/60' : ($bad ? 'bg-red-100' : 'hover:bg-gray-50') }}"
                                    style="left: {{ $f['x'] * $px }}px; top: {{ $f['y'] * $px }}px;
                                           width: {{ $f['w'] * $px }}px; height: {{ $f['h'] * $px }}px;
                                           border: 1px dashed {{ $bad ? '#dc2626' : '#cbd5e1' }};
                                           font-size: {{ max(6, $f['font_size']) }}px;
                                           text-align: {{ $f['align'] }};
                                           font-weight: {{ $f['weight'] === 'bold' ? '700' : '400' }};">
                                {{ $f['token'] === 'static' ? ($f['text'] ?: 'Text') : $f['token'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Live preview: the actual renderer, not a mock-up --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Preview</h3>
                <p class="text-xs text-gray-400 mb-3">
                    Rendered by the same code that prints, with sample data. This is what comes off the roll.
                </p>
                <div class="inline-block border border-gray-300 shadow-sm">
                    <iframe srcdoc="{{ $previewHtml }}"
                            style="width: {{ $w * $px }}px; height: {{ $h * $px }}px; border: 0;"
                            title="Label preview"></iframe>
                </div>
            </div>
        </div>

        {{-- Field list + editor --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Add a field</label>
                <div class="flex gap-2">
                    <select wire:model="newToken" class="flex-1 rounded-lg border-gray-300 text-sm">
                        @foreach ($tokens as $token => $label)
                            <option value="{{ $token }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button wire:click="addField" class="px-3 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Add</button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs text-gray-500">{{ count($fields) }} fields</p>
                </div>
                <div class="divide-y divide-gray-50 max-h-52 overflow-y-auto">
                    @foreach ($fields as $i => $f)
                        <button type="button" wire:click="selectField({{ $i }})" wire:key="list-{{ $i }}"
                                class="w-full text-left px-4 py-2 text-sm flex items-center justify-between
                                       {{ $selected === $i ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50 text-gray-600' }}">
                            <span>{{ $tokens[$f['token']] ?? $f['token'] }}</span>
                            @if (isset($overflow[$i]))
                                <span class="text-[10px] text-red-600">off label</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($selected !== null && isset($fields[$selected]))
                @php $f = $fields[$selected]; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">{{ $tokens[$f['token']] ?? $f['token'] }}</h3>
                        <button wire:click="removeField({{ $selected }})" class="text-xs text-red-500 hover:underline">Remove</button>
                    </div>

                    @if (isset($overflow[$selected]))
                        <p class="text-xs text-red-600">Off the label — {{ $overflow[$selected] }}.</p>
                    @endif

                    @if ($f['token'] === 'static')
                        <div>
                            <label class="text-xs text-gray-500">Text</label>
                            <input type="text" wire:model.live.debounce.400ms="fields.{{ $selected }}.text"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        @foreach ([['x', 'X (mm)'], ['y', 'Y (mm)'], ['w', 'Width'], ['h', 'Height']] as [$key, $label])
                            <div>
                                <label class="text-xs text-gray-500">{{ $label }}</label>
                                <input type="number" step="0.5" wire:model.live.debounce.400ms="fields.{{ $selected }}.{{ $key }}"
                                       class="mt-1 w-full text-sm rounded-lg border-gray-300">
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <label class="text-xs text-gray-500">Nudge</label>
                        <div class="mt-1 flex items-center gap-1">
                            <button wire:click="nudge({{ $selected }}, -1, 0)" class="px-2 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">←</button>
                            <button wire:click="nudge({{ $selected }}, 1, 0)" class="px-2 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">→</button>
                            <button wire:click="nudge({{ $selected }}, 0, -1)" class="px-2 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">↑</button>
                            <button wire:click="nudge({{ $selected }}, 0, 1)" class="px-2 py-1 text-xs border border-gray-200 rounded hover:bg-gray-50">↓</button>
                            <span class="ml-1 text-xs text-gray-400">1mm</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-gray-500">Size (pt)</label>
                            <input type="number" step="0.5" min="5" max="48" wire:model.live.debounce.400ms="fields.{{ $selected }}.font_size"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Align</label>
                            <select wire:model.live="fields.{{ $selected }}.align" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Weight</label>
                            <select wire:model.live="fields.{{ $selected }}.weight" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="normal">Normal</option>
                                <option value="bold">Bold</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400">
                        At 203dpi anything under 7pt prints ragged on thermal stock.
                    </p>
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                    <p class="text-sm text-gray-400">Select a field to edit it.</p>
                </div>
            @endif
        </div>
    </div>
</div>
