<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Reports</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($categories as $cat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $cat['icon'] }}" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">{{ $cat['title'] }}</h3>
                </div>
                <div class="space-y-1.5">
                    @foreach ($cat['reports'] as $report)
                        <a href="{{ route($report['route']) }}"
                           class="block text-sm text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 px-3 py-2 rounded-lg transition">
                            {{ $report['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
