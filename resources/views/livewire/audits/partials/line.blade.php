{{--
    One line of the audit as a row.

    A heading (parent) is a label with a running subtotal of its children. A
    scored leaf gets the three targets. A product slot is a heading with a
    text box for what was tasted. An info line is an input and nothing else.

    Every target is at least 44px — see the touch-target rule in CLAUDE.md;
    this screen is used with a tablet in one hand.
--}}
@php
    $editable = $isDraft && $canConduct;
    $isChild  = $line->parent_id !== null;
    $result   = $line->result;
@endphp

<li wire:key="line-{{ $line->id }}" class="{{ $isChild ? 'bg-gray-50/50 pl-4 sm:pl-6' : '' }}">
    @if (! $line->is_leaf)
        {{-- Heading / product slot --}}
        <div class="px-4 py-3">
            <div class="flex items-start gap-3">
                @if ($line->number)
                    <span class="mt-0.5 w-7 flex-shrink-0 text-sm font-semibold tabular-nums text-gray-500">{{ $line->number }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900">{{ $line->label }}</p>
                    @if ($line->label_alt)<p class="text-xs text-gray-600">{{ $line->label_alt }}</p>@endif
                    @if ($line->type === 'product')
                        <input type="text" wire:model.blur="subjects.{{ $line->id }}" class="input mt-2 max-w-sm"
                               placeholder="What was tasted — e.g. Kaya & butter toast" @disabled(! $editable) />
                    @endif
                </div>
            </div>
        </div>
    @elseif ($line->type === 'info')
        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
            @if ($line->number)<span class="w-7 flex-shrink-0 text-sm tabular-nums text-gray-500">{{ $line->number }}</span>@endif
            <div class="min-w-[12rem] flex-1">
                <p class="text-sm text-gray-900">{{ $line->label }}</p>
                @if ($line->label_alt)<p class="text-xs text-gray-600">{{ $line->label_alt }}</p>@endif
            </div>
            @if ($line->info_type === 'textarea')
                <textarea wire:model.blur="infoValues.{{ $line->id }}" rows="2" class="input w-full sm:w-72" @disabled(! $editable)></textarea>
            @else
                <input type="{{ $line->info_type === 'number' ? 'number' : ($line->info_type === 'time' ? 'time' : 'text') }}"
                       wire:model.blur="infoValues.{{ $line->id }}" class="input w-full sm:w-48" @disabled(! $editable) />
            @endif
        </div>
    @else
        <div class="px-4 py-3 {{ $result === 'nc' ? 'bg-danger-50/40' : '' }}">
            {{-- Stacked on a phone: the label gets the full width and the three
                 targets sit under it, right-aligned under the thumb. Side by
                 side from sm up, where there is room for both. --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    @if ($line->number)
                        <span class="mt-0.5 w-7 flex-shrink-0 text-sm tabular-nums text-gray-500 sm:mt-2.5">{{ $line->number }}</span>
                    @endif
                    <div class="min-w-0 flex-1 sm:pt-2">
                        <p class="text-sm text-gray-900">{{ $line->label }}</p>
                        @if ($line->label_alt)<p class="text-xs text-gray-600">{{ $line->label_alt }}</p>@endif
                        @if ($line->hint)<p class="mt-0.5 text-xs text-brand-700">{{ $line->hint }}</p>@endif
                    </div>
                </div>
                <div class="flex flex-shrink-0 items-center justify-end gap-1.5 pl-10 sm:pl-0">
                    <span class="w-10 text-right text-xs tabular-nums text-gray-500">{{ $line->points }} pt</span>
                    <div class="inline-flex overflow-hidden rounded-control border border-gray-300 bg-white" role="group" aria-label="Result">
                        @foreach (['ok' => ['OK', 'bg-success-600 text-white'], 'nc' => ['NC', 'bg-danger-600 text-white'], 'na' => ['N/A', 'bg-gray-700 text-white']] as $key => [$label, $on])
                            <button type="button" wire:click="answer({{ $line->id }}, '{{ $key }}')"
                                    class="flex h-11 w-12 items-center justify-center text-xs font-semibold transition
                                           {{ $result === $key ? $on : 'text-gray-700 hover:bg-gray-100' }}
                                           {{ ! $loop->last ? 'border-r border-gray-300' : '' }}"
                                    @disabled(! $editable) aria-pressed="{{ $result === $key ? 'true' : 'false' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($result === 'nc')
                <div class="mt-3 space-y-3 pl-0 sm:pl-10">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-medium text-gray-700">Points lost</span>
                        <div class="stepper">
                            <button type="button" class="stepper-btn" wire:click="adjustPointsLost({{ $line->id }}, -1)" @disabled(! $editable || $line->points_lost <= 0) aria-label="Fewer">−</button>
                            <span class="stepper-val">{{ $line->points_lost }}</span>
                            <button type="button" class="stepper-btn" wire:click="adjustPointsLost({{ $line->id }}, 1)" @disabled(! $editable || $line->points_lost >= $line->points) aria-label="More">+</button>
                        </div>
                        <span class="text-xs text-gray-600">of {{ $line->points }}</span>
                    </div>

                    <textarea wire:model.blur="lineNotes.{{ $line->id }}" rows="2" class="input"
                              placeholder="What was found — this becomes the finding" @disabled(! $editable)></textarea>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($photos as $photo)
                            <div wire:key="photo-{{ $photo->id }}" class="relative" x-data="{ zoom: false }">
                                <button type="button" @click="zoom = true" class="block h-16 w-16 overflow-hidden rounded-control border border-gray-200">
                                    <img src="{{ $photo->url() }}" alt="" loading="lazy" class="h-full w-full object-cover" />
                                </button>
                                @if ($editable)
                                    <button type="button" wire:click="removePhoto({{ $photo->id }})"
                                            data-confirm-delete="Remove this photo from the finding. It is deleted from storage."
                                            class="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-white shadow"
                                            aria-label="Remove photo">
                                        <x-icon name="close" size="h-3 w-3" />
                                    </button>
                                @endif
                                <template x-teleport="body">
                                    <div x-show="zoom" x-cloak @keydown.escape.window="zoom = false" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                                        <div class="fixed inset-0 bg-gray-900/80" @click="zoom = false"></div>
                                        <img src="{{ $photo->url() }}" alt="" class="relative max-h-[85vh] max-w-[95vw] rounded-panel object-contain shadow-e4" @click="zoom = false" />
                                    </div>
                                </template>
                            </div>
                        @endforeach

                        @if ($editable && $photos->count() < 6)
                            <label class="flex h-16 w-16 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-control border border-dashed border-gray-300 text-gray-600 hover:border-brand-400 hover:text-brand-700">
                                <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="photos.{{ $line->id }}" />
                                <x-icon name="device" size="h-5 w-5" />
                                <span class="text-[10px] font-medium">
                                    <span wire:loading.remove wire:target="photos.{{ $line->id }}">Photo</span>
                                    <span wire:loading wire:target="photos.{{ $line->id }}">…</span>
                                </span>
                            </label>
                        @endif
                    </div>
                    @error("photos.{$line->id}") <p class="error-text">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    @endif
</li>
