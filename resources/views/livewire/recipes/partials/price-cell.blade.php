{{-- Inline-editable selling price cell.
     Vars: $recipe, $priceClassId (0 = legacy recipes.selling_price), $value (float|null), $locked --}}
@php
    $display = floatval($value) > 0 ? number_format(floatval($value), 2, '.', '') : '';
@endphp
<td class="px-4 py-3 text-right tabular-nums text-gray-700" wire:key="price-{{ $recipe->id }}-{{ $priceClassId }}">
    @if ($locked)
        @if ($display !== '')
            {{ number_format(floatval($display), 2) }}
        @else
            <span class="text-gray-300">—</span>
        @endif
    @else
        <div x-data="{ editing: false, val: @js($display), orig: @js($display), cancel: false }" class="flex justify-end">
            <button type="button" x-show="! editing"
                    @click="editing = true; $nextTick(() => { $refs.inp.focus(); $refs.inp.select(); })"
                    class="group inline-flex items-center gap-1 hover:text-indigo-600 transition"
                    title="Click to edit price">
                <span x-text="val !== '' ? Number(val).toFixed(2) : '—'"
                      :class="val === '' ? 'text-gray-300 group-hover:text-indigo-400' : ''"></span>
                <svg class="w-3 h-3 text-gray-300 opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <input x-show="editing" x-cloak x-ref="inp" x-model="val"
                   type="number" step="0.01" min="0" max="999999"
                   class="w-24 text-right rounded border-gray-300 text-sm py-0.5 px-1.5 focus:border-indigo-500 focus:ring-indigo-500"
                   @keydown.enter.prevent="$el.blur()"
                   @keydown.escape.prevent="cancel = true; $el.blur()"
                   @blur="editing = false;
                          if (cancel) { val = orig; cancel = false; }
                          else if (val !== orig) { $wire.updatePrice({{ $recipe->id }}, {{ $priceClassId }}, val).then(() => orig = val); }" />
        </div>
    @endif
</td>
