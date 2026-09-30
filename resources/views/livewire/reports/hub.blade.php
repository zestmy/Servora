<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="page-title">Reports</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($categories as $cat)
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-brand-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $cat['icon'] }}" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">{{ $cat['title'] }}</h3>
                </div>
                <div class="space-y-1.5">
                    @foreach ($cat['reports'] as $report)
                        @if (! empty($report['locked']))
                            {{-- Not on the plan: goes to Billing, as the sidebar's locked items do. --}}
                            <a href="{{ route('billing.index', ['unlock' => $report['locked']]) }}"
                               class="flex items-center gap-2 text-sm text-gray-600 hover:text-brand-600 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                                <span class="min-w-0 flex-1">{{ $report['label'] }}</span>
                                <x-icon name="lock" size="h-3.5 w-3.5" stroke="1.8" class="flex-shrink-0 text-gray-500" />
                                <span class="sr-only">(upgrade to unlock)</span>
                            </a>
                        @else
                            <a href="{{ route($report['route']) }}"
                               class="block text-sm text-gray-600 hover:text-brand-600 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                                {{ $report['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
