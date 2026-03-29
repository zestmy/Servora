<div>
    @if ($hidden ?? false)
        {{-- Purchasing-only role — no switcher needed --}}
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Switch Outlet</h3>
            <p class="text-xs text-gray-400 mb-4">Select which outlet to view. All data across the app will be scoped to your selection.</p>

            {{-- Outlets Section --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if ($canViewAll)
                    <button wire:click="switchOutlet('')"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg border-2 text-left transition
                                   {{ $activeOutletId === '' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center {{ $activeOutletId === '' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium {{ $activeOutletId === '' ? 'text-indigo-700' : 'text-gray-700' }}">All Outlets</p>
                            <p class="text-xs {{ $activeOutletId === '' ? 'text-indigo-500' : 'text-gray-400' }}">Aggregated view</p>
                        </div>
                        @if ($activeOutletId === '')
                            <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        @endif
                    </button>
                @endif

                @foreach ($outlets as $outlet)
                    <button wire:click="switchOutlet('{{ $outlet->id }}')"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg border-2 text-left transition
                                   {{ (string) $outlet->id === $activeOutletId ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center {{ (string) $outlet->id === $activeOutletId ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium {{ (string) $outlet->id === $activeOutletId ? 'text-indigo-700' : 'text-gray-700' }}">{{ $outlet->name }}</p>
                            @if ($outlet->address)
                                <p class="text-xs {{ (string) $outlet->id === $activeOutletId ? 'text-indigo-500' : 'text-gray-400' }} truncate">{{ $outlet->address }}</p>
                            @endif
                        </div>
                        @if ((string) $outlet->id === $activeOutletId)
                            <svg class="h-5 w-5 text-indigo-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Central Kitchen Section --}}
            @if ($kitchenOutlets->count() > 0)
                <div class="mt-6 pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-purple-600 uppercase tracking-wider mb-3">Central Kitchen</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($kitchenOutlets as $outlet)
                            <button wire:click="switchOutlet('{{ $outlet->id }}')"
                                    class="flex items-center gap-3 px-4 py-3 rounded-lg border-2 text-left transition
                                           {{ (string) $outlet->id === $activeOutletId ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-300 hover:bg-purple-50/30' }}">
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center {{ (string) $outlet->id === $activeOutletId ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-400' }}">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium {{ (string) $outlet->id === $activeOutletId ? 'text-purple-700' : 'text-gray-700' }}">{{ $outlet->name }}</p>
                                    @if ($outlet->address)
                                        <p class="text-xs {{ (string) $outlet->id === $activeOutletId ? 'text-purple-500' : 'text-gray-400' }} truncate">{{ $outlet->address }}</p>
                                    @endif
                                </div>
                                @if ((string) $outlet->id === $activeOutletId)
                                    <svg class="h-5 w-5 text-purple-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
