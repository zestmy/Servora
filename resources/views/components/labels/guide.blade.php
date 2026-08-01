{{--
    Which label type to use, shown wherever one is chosen.

    A <details> rather than a modal or an Alpine panel: it collapses natively,
    needs no JavaScript, keeps its state while Livewire re-renders around it,
    and behaves the same on a phone as on a desktop. Closed by default —
    people who know the answer should not have to scroll past it.

    Props:
      compact  tighter type for the staff app on a phone
--}}
@props(['compact' => false])

@php
    $guide  = \App\Services\Labels\LabelGuide::class;
    $states = $guide::states(
        \Illuminate\Support\Facades\Auth::user()?->company_id
            ?? (app()->bound('currentCompany') ? app('currentCompany')?->id : null)
    );
    $pad = $compact ? 'px-4 py-3' : 'px-5 py-4';
@endphp

<details {{ $attributes->merge(['class' => 'group bg-white rounded-xl border border-gray-200 overflow-hidden']) }}>
    <summary class="{{ $pad }} cursor-pointer select-none flex items-center justify-between gap-3 list-none">
        <span class="flex items-center gap-2 min-w-0">
            <svg class="w-4 h-4 shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-semibold text-gray-700 truncate">Which label do I use?</span>
        </span>
        <span class="text-xs text-gray-400 shrink-0 group-open:hidden">Show</span>
        <span class="text-xs text-gray-400 shrink-0 hidden group-open:inline">Hide</span>
    </summary>

    <div class="{{ $pad }} border-t border-gray-100 space-y-3">
        @foreach (\App\Services\Labels\LabelGuide::types() as $type)
            <div class="rounded-lg border border-gray-100 {{ $compact ? 'p-3' : 'p-3.5' }}">
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="px-2 py-0.5 rounded bg-gray-900 text-white text-[11px] font-bold tracking-wider">
                        {{ $type['caption'] }}
                    </span>
                    <span class="text-sm font-medium text-gray-700">{{ $type['title'] }}</span>
                </div>

                <p class="mt-1.5 text-xs text-gray-600">{{ $type['when'] }}</p>
                <p class="mt-1 text-xs text-gray-500"><span class="font-medium">Date printed:</span> {{ $type['date'] }}</p>

                @if ($type['watch'])
                    <p class="mt-1 text-xs text-amber-700"><span class="font-medium">Watch out:</span> {{ $type['watch'] }}</p>
                @endif
            </div>
        @endforeach

        <div class="rounded-lg bg-gray-50 border border-gray-100 {{ $compact ? 'p-3' : 'p-3.5' }}">
            <p class="text-xs font-semibold text-gray-600 mb-1.5">Storage state sets the temperature on the label</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($states as $state)
                    <span class="px-2 py-0.5 rounded-full bg-white border border-gray-200 text-[11px] text-gray-600">
                        {{ $state['label'] }}@if ($state['range']) · {{ $state['range'] }} @endif
                    </span>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">
                It also picks which shelf-life rule applies, so changing it changes the date.
                Opened has no temperature of its own — it describes the pack, not how to hold it.
            </p>
        </div>

        <div class="rounded-lg bg-gray-50 border border-gray-100 {{ $compact ? 'p-3' : 'p-3.5' }}">
            <p class="text-xs font-semibold text-gray-600 mb-1.5">Where the use-by comes from</p>
            <ol class="list-decimal list-inside space-y-0.5 text-xs text-gray-600">
                @foreach (\App\Services\Labels\LabelGuide::dateSources() as $source)
                    <li>{{ $source }}</li>
                @endforeach
            </ol>
        </div>
    </div>
</details>
