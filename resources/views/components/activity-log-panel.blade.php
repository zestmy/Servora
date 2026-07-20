{{--
    Slide-over panel listing recent audit activity for a list page — who
    added, updated or deleted which records. Driven by a Livewire bool
    property named `showActivityLog` on the parent component:

    <x-activity-log-panel :show="$showActivityLog" title="Market List Activity"
        :logs="$activityLogs" :labels="$activityLabels" :view-all-url="route(...)" />

    $labels comes from AuditLogService::recordLabels($logs) — resolves record
    names (soft-deleted included) so "who deleted what" stays readable.
--}}
@props(['show' => false, 'title' => 'Recent Activity', 'logs' => null, 'labels' => [], 'viewAllUrl' => null])

@if ($show)
    <div class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/30" wire:click="$set('showActivityLog', false)"></div>
        <div class="relative bg-white w-full max-w-lg h-full shadow-2xl flex flex-col"
             x-data x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">{{ $title }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Who added, updated or deleted what — newest first</p>
                </div>
                <button wire:click="$set('showActivityLog', false)" class="text-gray-400 hover:text-gray-600 p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto divide-y divide-gray-50 px-5">
                @forelse ($logs ?? [] as $log)
                    @php
                        $label = $labels[$log->auditable_type . ':' . $log->auditable_id] ?? ('#' . $log->auditable_id);
                        [$chipClass, $chipText] = match ($log->event) {
                            'created'                   => ['bg-green-50 text-green-600', 'Added'],
                            'updated'                   => ['bg-amber-50 text-amber-600', 'Updated'],
                            'deleted', 'force_deleted'  => ['bg-red-50 text-red-600', 'Deleted'],
                            'restored'                  => ['bg-blue-50 text-blue-600', 'Restored'],
                            default                     => ['bg-gray-50 text-gray-500', ucfirst(str_replace('_', ' ', $log->event))],
                        };
                    @endphp
                    <div class="py-3">
                        <div class="flex items-center gap-2">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide flex-shrink-0 {{ $chipClass }}">{{ $chipText }}</span>
                            <span class="text-sm font-medium text-gray-800 truncate" title="{{ $label }}">{{ $label }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $log->summary() }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $log->actorName() }} · {{ $log->created_at?->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="py-8 text-sm text-gray-400 text-center">No recorded activity yet.</p>
                @endforelse
            </div>
            @if ($viewAllUrl)
                <div class="px-5 py-3 border-t border-gray-100 flex-shrink-0">
                    <a href="{{ $viewAllUrl }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all in Audit Logs →</a>
                </div>
            @endif
        </div>
    </div>
@endif
