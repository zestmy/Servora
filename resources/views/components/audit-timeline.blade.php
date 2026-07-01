@props([
    'type',
    'id' => null,
    'limit' => 6,
    'title' => 'Recent Activity',
])

@php
    $entries = $id ? \App\Services\AuditLogService::recentFor($type, $id, $limit) : collect();
@endphp

@if ($entries->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100 p-4']) }}>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $title }}</h3>
            @can('audit.view')
                <a href="{{ route('audit-logs.index', ['q' => (string) $id, 'typeFilter' => $type, 'quickRange' => 'all']) }}"
                   class="text-[11px] text-indigo-500 hover:text-indigo-600">View all</a>
            @endcan
        </div>

        <ul class="space-y-0">
            @foreach ($entries as $i => $log)
                <li class="relative flex items-start gap-3 pb-4 last:pb-0">
                    {{-- Connector line (not on the last item) --}}
                    @unless ($loop->last)
                        <span class="absolute left-[9px] top-5 bottom-0 w-px bg-emerald-200"></span>
                    @endunless

                    {{-- Check node --}}
                    <span class="relative z-10 flex-shrink-0 mt-0.5 h-[18px] w-[18px] rounded-full bg-white border-2 border-emerald-400 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>

                    {{-- Text + date --}}
                    <div class="flex-1 min-w-0 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                        <p class="text-sm text-gray-700">
                            {{ $log->summary() }}
                            <span class="text-gray-400">by</span>
                            <span class="font-semibold text-gray-800">{{ $log->actorName() }}</span>
                        </p>
                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ $log->created_at?->format('D, M d Y') }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
