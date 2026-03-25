<div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-center">
    <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-800">Plan Limit Reached</h3>
    <p class="text-xs text-gray-500 mt-1">{{ $message }}</p>
    @if ($limit)
        <div class="mt-3 flex items-center justify-center gap-2">
            <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full" style="width: {{ min(100, ($current / max($limit, 1)) * 100) }}%"></div>
            </div>
            <span class="text-xs text-gray-500 font-medium">{{ $current }}/{{ $limit }}</span>
        </div>
    @endif
    <a href="{{ route('billing.index') }}"
       class="inline-block mt-4 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
        Upgrade Plan
    </a>
</div>
