@if ($announcements->isNotEmpty())
    <div class="space-y-2 mb-4">
        @foreach ($announcements as $announcement)
            @php $c = $announcement->typeColor(); @endphp
            <div x-data="{ show: true }" x-show="show"
                 class="px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg flex items-start gap-3">
                <div class="flex-1">
                    <p class="text-sm font-medium text-{{ $c }}-800">{{ $announcement->title }}</p>
                    <p class="text-xs text-{{ $c }}-600 mt-0.5">{{ $announcement->body }}</p>
                </div>
                <button @click="show = false" class="text-{{ $c }}-400 hover:text-{{ $c }}-600 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endforeach
    </div>
@endif
