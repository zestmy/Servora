<div x-show="sidebarExpanded"
     x-transition:enter="transition-opacity duration-150 delay-100"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-75"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="px-3 pt-3 pb-2 space-y-1">

    @if ($companies->count() > 1)
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.away="open = false"
                    class="flex items-center gap-2 w-full px-1 py-1 rounded-lg hover:bg-gray-800 transition text-left">
                <span class="text-sm leading-none">🏢</span>
                <span class="flex-1 text-xs font-medium text-gray-300 truncate">
                    {{ $companies->firstWhere('id', $activeCompanyId)?->name ?? '—' }}
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                </svg>
            </button>

            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute bottom-full left-0 right-0 mb-1 py-1 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-50 max-h-64 overflow-y-auto">
                @foreach ($companies as $company)
                    <button wire:click="switchCompany({{ $company->id }})"
                            class="flex items-center gap-2 w-full px-3 py-2 text-left text-xs transition
                                   {{ $company->id === $activeCompanyId ? 'text-white bg-gray-700/60' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                        <span class="flex-1 truncate">{{ $company->name }}</span>
                        @if ($company->id === $activeCompanyId)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @endif
                    </button>
                @endforeach

                @if ($canCreate)
                    <a href="{{ route('company.create') }}"
                       class="flex items-center gap-2 w-full px-3 py-2 text-left text-xs text-indigo-300 hover:bg-gray-700 hover:text-indigo-200 transition border-t border-gray-700 mt-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create New Company</span>
                    </a>
                @endif
            </div>
        </div>
    @else
        <div class="flex items-center gap-2 px-1">
            <span class="text-sm leading-none">🏢</span>
            <span class="flex-1 text-xs font-medium text-gray-300 truncate">
                {{ $companies->first()?->name ?? (Auth::user()->company->name ?? '—') }}
            </span>
            @if ($canCreate)
                <a href="{{ route('company.create') }}" title="Create a new company"
                   class="p-0.5 rounded text-gray-500 hover:text-indigo-300 hover:bg-gray-800 transition flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </a>
            @endif
        </div>
    @endif
</div>
