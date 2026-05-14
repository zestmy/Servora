<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">AI Analysis</h2>
    </div>

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @unless ($hasApiKey)
        <div class="mb-6 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-lg flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>AI Analysis requires an OpenRouter API key. Please configure it in <a href="{{ route('settings.api-keys') }}" class="underline font-medium">Settings > API Keys</a>.</span>
        </div>
    @endunless

    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        <button wire:click="switchTab('generate')"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ $activeTab === 'generate' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Generate Analysis
        </button>
        <button wire:click="switchTab('saved')"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ $activeTab === 'saved' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Saved Reports
            <span class="ml-1 px-1.5 py-0.5 bg-gray-200 text-gray-600 text-xs rounded-full">{{ $savedReports->total() }}</span>
        </button>
    </div>

    @if ($activeTab === 'generate')
        {{-- Filter bar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                {{-- Period --}}
                <div class="flex items-center gap-2">
                    <button wire:click="previousMonth" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-800 min-w-[120px] text-center">{{ $periodLabel }}</span>
                    <button wire:click="nextMonth" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="h-6 w-px bg-gray-200"></div>

                {{-- Outlet --}}
                <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All Outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left: Controls --}}
            <div class="space-y-4">
                {{-- Analysis Type --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Analysis Type</label>
                    <div class="space-y-2">
                        @foreach ($analysisTypes as $value => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="analysisType" value="{{ $value }}"
                                       class="text-indigo-600 focus:ring-indigo-500 border-gray-300" />
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($analysisType === 'weekly_review')
                        <div class="mt-3">
                            <x-input-label value="Week Starting" />
                            <input type="date" wire:model="weekStart"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">Select the Monday of the week to review.</p>
                        </div>
                    @elseif ($analysisType === 'custom')
                        <textarea wire:model="customQuestion" rows="3" placeholder="Type your question..."
                                  class="mt-3 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @endif
                </div>

                {{-- Run Button --}}
                <button wire:click="runAnalysis"
                        wire:loading.attr="disabled"
                        wire:target="runAnalysis"
                        @disabled(!$hasApiKey)
                        class="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="runAnalysis">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <span wire:loading wire:target="runAnalysis">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="runAnalysis">Run AI Analysis</span>
                    <span wire:loading wire:target="runAnalysis">Analyzing...</span>
                </button>

                <p class="text-xs text-gray-400 text-center">AI will analyze your operational data for {{ $periodLabel }} and provide actionable insights.</p>
            </div>

            {{-- Right: Response --}}
            <div class="lg:col-span-2">
                {{-- Loading --}}
                <div wire:loading wire:target="runAnalysis"
                     class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 flex flex-col items-center justify-center">
                    <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-sm font-medium text-gray-600">Analyzing your data with AI...</p>
                    <p class="text-xs text-gray-400 mt-1">This may take up to 60 seconds</p>
                </div>

                <div wire:loading.remove wire:target="runAnalysis">
                    @if ($error)
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                            <div class="flex items-center gap-2 font-semibold mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Error
                            </div>
                            {{ $error }}
                        </div>
                    @elseif ($isMultiOutlet && count($outletResults) > 0)
                        {{-- Multi-Outlet Results --}}
                        <div class="space-y-4">
                            {{-- Report Header --}}
                            <div class="bg-white rounded-xl shadow-sm border border-indigo-200 overflow-hidden">
                                <div class="px-6 py-4 bg-indigo-50/50 flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-800">Multi-Outlet Analysis</h3>
                                            <p class="text-xs text-gray-400">{{ $respondedAt }}{{ $cached ? ' (cached)' : '' }} &middot; {{ count($outletResults) }} outlets analyzed</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">Powered by Servora AI</div>
                                </div>
                            </div>

                            {{-- Outlet Tabs --}}
                            <div class="flex gap-1 bg-gray-100 rounded-lg p-1 overflow-x-auto">
                                @foreach ($outletResults as $index => $outletResult)
                                    <button wire:click="setActiveOutletTab({{ $index }})"
                                            class="px-4 py-2 text-sm font-medium rounded-md transition whitespace-nowrap {{ $activeOutletTab === $index ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                        {{ $outletResult['outlet_name'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Active Outlet Content --}}
                            @if (isset($outletResults[$activeOutletTab]))
                                @php
                                    $currentOutlet = $outletResults[$activeOutletTab];
                                    $outletInsights = $currentOutlet['insights'] ?? null;
                                    $outletResponse = $currentOutlet['response'] ?? '';
                                @endphp

                                <div class="space-y-4">
                                    @if ($outletInsights)
                                        {{-- Headline & Score --}}
                                        @if (!empty($outletInsights['headline']))
                                            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl p-6 text-white">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <div class="text-xs font-medium text-indigo-200 mb-1">{{ $currentOutlet['outlet_name'] }}</div>
                                                        <p class="text-lg font-semibold">{{ $outletInsights['headline'] }}</p>
                                                        @if (!empty($outletInsights['performance_score']))
                                                            @php
                                                                $scoreColors = [
                                                                    'excellent' => 'bg-green-400',
                                                                    'good' => 'bg-emerald-400',
                                                                    'average' => 'bg-yellow-400',
                                                                    'below_average' => 'bg-orange-400',
                                                                    'poor' => 'bg-red-400',
                                                                ];
                                                                $scoreLabels = [
                                                                    'excellent' => 'Excellent',
                                                                    'good' => 'Good',
                                                                    'average' => 'Average',
                                                                    'below_average' => 'Below Average',
                                                                    'poor' => 'Needs Attention',
                                                                ];
                                                            @endphp
                                                            <span class="inline-flex items-center mt-2 px-2.5 py-1 rounded-full text-xs font-medium {{ $scoreColors[$outletInsights['performance_score']] ?? 'bg-gray-400' }} text-gray-900">
                                                                {{ $scoreLabels[$outletInsights['performance_score']] ?? ucfirst(str_replace('_', ' ', $outletInsights['performance_score'])) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Key Metrics --}}
                                        @if (!empty($outletInsights['key_metrics']))
                                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                                @foreach ($outletInsights['key_metrics'] as $metric)
                                                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                                        <div class="text-xl font-bold text-gray-800">{{ $metric['value'] ?? '-' }}</div>
                                                        <div class="text-xs font-medium text-gray-500 mt-1">{{ $metric['label'] ?? '' }}</div>
                                                        @if (!empty($metric['trend']))
                                                            <div class="mt-1 text-xs {{ $metric['trend'] === 'up' ? 'text-green-600' : ($metric['trend'] === 'down' ? 'text-red-600' : 'text-gray-400') }}">
                                                                {{ $metric['trend'] === 'up' ? '↑' : ($metric['trend'] === 'down' ? '↓' : '→') }}
                                                                {{ $metric['note'] ?? '' }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Highlights & Concerns --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @if (!empty($outletInsights['highlights']))
                                                <div class="bg-green-50 rounded-xl border border-green-100 p-4">
                                                    <div class="flex items-center gap-2 text-green-700 font-semibold text-sm mb-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Highlights
                                                    </div>
                                                    <ul class="space-y-2">
                                                        @foreach ($outletInsights['highlights'] as $highlight)
                                                            <li class="text-sm text-green-800 flex items-start gap-2">
                                                                <span class="text-green-500 mt-0.5">•</span>
                                                                {{ $highlight }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            @if (!empty($outletInsights['concerns']))
                                                <div class="bg-red-50 rounded-xl border border-red-100 p-4">
                                                    <div class="flex items-center gap-2 text-red-700 font-semibold text-sm mb-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                        Areas of Attention
                                                    </div>
                                                    <ul class="space-y-2">
                                                        @foreach ($outletInsights['concerns'] as $concern)
                                                            <li class="text-sm text-red-800 flex items-start gap-2">
                                                                <span class="text-red-500 mt-0.5">•</span>
                                                                {{ $concern }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Recommendations --}}
                                        @if (!empty($outletInsights['recommendations']))
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                                <div class="flex items-center gap-2 text-indigo-700 font-semibold text-sm mb-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                                    Recommendations
                                                </div>
                                                <div class="space-y-3">
                                                    @foreach ($outletInsights['recommendations'] as $rec)
                                                        @php
                                                            $isArray = is_array($rec);
                                                            $title = $isArray ? ($rec['title'] ?? '') : $rec;
                                                            $description = $isArray ? ($rec['description'] ?? '') : '';
                                                            $priority = $isArray ? ($rec['priority'] ?? 'medium') : 'medium';
                                                            $priorityColors = [
                                                                'high' => 'bg-red-100 text-red-700',
                                                                'medium' => 'bg-yellow-100 text-yellow-700',
                                                                'low' => 'bg-gray-100 text-gray-600',
                                                            ];
                                                        @endphp
                                                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                                            <span class="px-2 py-0.5 text-xs font-medium rounded {{ $priorityColors[$priority] ?? 'bg-gray-100 text-gray-600' }}">
                                                                {{ ucfirst($priority) }}
                                                            </span>
                                                            <div class="flex-1">
                                                                <div class="text-sm font-medium text-gray-800">{{ $title }}</div>
                                                                @if ($description)
                                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $description }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Detailed Analysis --}}
                                        @if (!empty($outletInsights['detailed_analysis']))
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                                <div class="flex items-center gap-2 text-gray-700 font-semibold text-sm mb-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Detailed Analysis
                                                </div>
                                                <div class="prose prose-sm max-w-none prose-headings:text-gray-800 prose-p:text-gray-600">
                                                    {!! Str::markdown($outletInsights['detailed_analysis']) !!}
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        {{-- Fallback: Plain markdown --}}
                                        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                                            <div class="px-6 py-5 prose prose-sm max-w-none prose-headings:text-gray-800">
                                                {!! Str::markdown($outletResponse) !!}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @elseif ($responseText)
                        <div class="space-y-4">
                            {{-- Report Header --}}
                            <div class="bg-white rounded-xl shadow-sm border border-indigo-200 overflow-hidden">
                                <div class="px-6 py-4 bg-indigo-50/50 flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-800">AI Analysis Report</h3>
                                            <p class="text-xs text-gray-400">{{ $respondedAt }}{{ $cached ? ' (cached)' : '' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-xs text-gray-400">Powered by Servora AI</div>
                                        <button wire:click="exportPdf"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            PDF
                                        </button>
                                    </div>
                                </div>
                            </div>

                            @if ($insights)
                                {{-- Headline & Score --}}
                                @if (!empty($insights['headline']))
                                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl p-6 text-white">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-lg font-semibold">{{ $insights['headline'] }}</p>
                                                @if (!empty($insights['performance_score']))
                                                    @php
                                                        $scoreColors = [
                                                            'excellent' => 'bg-green-400',
                                                            'good' => 'bg-emerald-400',
                                                            'average' => 'bg-yellow-400',
                                                            'below_average' => 'bg-orange-400',
                                                            'poor' => 'bg-red-400',
                                                        ];
                                                        $scoreLabels = [
                                                            'excellent' => 'Excellent',
                                                            'good' => 'Good',
                                                            'average' => 'Average',
                                                            'below_average' => 'Below Average',
                                                            'poor' => 'Needs Attention',
                                                        ];
                                                    @endphp
                                                    <span class="inline-flex items-center mt-2 px-2.5 py-1 rounded-full text-xs font-medium {{ $scoreColors[$insights['performance_score']] ?? 'bg-gray-400' }} text-gray-900">
                                                        {{ $scoreLabels[$insights['performance_score']] ?? ucfirst(str_replace('_', ' ', $insights['performance_score'])) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Key Metrics --}}
                                @if (!empty($insights['key_metrics']))
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach ($insights['key_metrics'] as $metric)
                                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                                <div class="text-xl font-bold text-gray-800">{{ $metric['value'] ?? '-' }}</div>
                                                <div class="text-xs font-medium text-gray-500 mt-1">{{ $metric['label'] ?? '' }}</div>
                                                @if (!empty($metric['trend']))
                                                    <div class="mt-1 text-xs {{ $metric['trend'] === 'up' ? 'text-green-600' : ($metric['trend'] === 'down' ? 'text-red-600' : 'text-gray-400') }}">
                                                        {{ $metric['trend'] === 'up' ? '↑' : ($metric['trend'] === 'down' ? '↓' : '→') }}
                                                        {{ $metric['note'] ?? '' }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Outlets Performance (Multi-outlet) --}}
                                @if (!empty($insights['outlets_summary']))
                                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                        <div class="flex items-center gap-2 text-gray-700 font-semibold text-sm mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            Performance by Outlet
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="border-b border-gray-100">
                                                        <th class="text-left py-2 pr-4 font-medium text-gray-500">Outlet</th>
                                                        <th class="text-right py-2 px-4 font-medium text-gray-500">Revenue</th>
                                                        <th class="text-center py-2 px-4 font-medium text-gray-500">Trend</th>
                                                        <th class="text-left py-2 pl-4 font-medium text-gray-500">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    @foreach ($insights['outlets_summary'] as $outlet)
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="py-2.5 pr-4 font-medium text-gray-800">{{ $outlet['name'] ?? '-' }}</td>
                                                            <td class="py-2.5 px-4 text-right text-gray-700">{{ $outlet['revenue'] ?? '-' }}</td>
                                                            <td class="py-2.5 px-4 text-center">
                                                                @php $trend = $outlet['trend'] ?? 'flat'; @endphp
                                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                                                    {{ $trend === 'up' ? 'bg-green-100 text-green-700' : ($trend === 'down' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                                                    @if ($trend === 'up')
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                                        Up
                                                                    @elseif ($trend === 'down')
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                                        Down
                                                                    @else
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                                                                        Flat
                                                                    @endif
                                                                </span>
                                                            </td>
                                                            <td class="py-2.5 pl-4 text-gray-500 text-xs">{{ $outlet['note'] ?? '' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                {{-- Highlights & Concerns --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @if (!empty($insights['highlights']))
                                        <div class="bg-green-50 rounded-xl border border-green-100 p-4">
                                            <div class="flex items-center gap-2 text-green-700 font-semibold text-sm mb-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Highlights
                                            </div>
                                            <ul class="space-y-2">
                                                @foreach ($insights['highlights'] as $highlight)
                                                    <li class="text-sm text-green-800 flex items-start gap-2">
                                                        <span class="text-green-500 mt-0.5">•</span>
                                                        {{ $highlight }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if (!empty($insights['concerns']))
                                        <div class="bg-red-50 rounded-xl border border-red-100 p-4">
                                            <div class="flex items-center gap-2 text-red-700 font-semibold text-sm mb-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                Areas of Attention
                                            </div>
                                            <ul class="space-y-2">
                                                @foreach ($insights['concerns'] as $concern)
                                                    <li class="text-sm text-red-800 flex items-start gap-2">
                                                        <span class="text-red-500 mt-0.5">•</span>
                                                        {{ $concern }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>

                                {{-- Recommendations --}}
                                @if (!empty($insights['recommendations']))
                                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                                        <div class="flex items-center gap-2 text-indigo-700 font-semibold text-sm mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                            Recommendations
                                        </div>
                                        <div class="space-y-3">
                                            @foreach ($insights['recommendations'] as $rec)
                                                @php
                                                    $isArray = is_array($rec);
                                                    $title = $isArray ? ($rec['title'] ?? '') : $rec;
                                                    $description = $isArray ? ($rec['description'] ?? '') : '';
                                                    $priority = $isArray ? ($rec['priority'] ?? 'medium') : 'medium';
                                                    $priorityColors = [
                                                        'high' => 'bg-red-100 text-red-700',
                                                        'medium' => 'bg-yellow-100 text-yellow-700',
                                                        'low' => 'bg-gray-100 text-gray-600',
                                                    ];
                                                @endphp
                                                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                                    <span class="px-2 py-0.5 text-xs font-medium rounded {{ $priorityColors[$priority] ?? 'bg-gray-100 text-gray-600' }}">
                                                        {{ ucfirst($priority) }}
                                                    </span>
                                                    <div class="flex-1">
                                                        <div class="text-sm font-medium text-gray-800">{{ $title }}</div>
                                                        @if ($description)
                                                            <div class="text-xs text-gray-500 mt-0.5">{{ $description }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Detailed Analysis (Markdown) --}}
                                @if (!empty($insights['detailed_analysis']))
                                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                        <div class="flex items-center gap-2 text-gray-700 font-semibold text-sm mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Detailed Analysis
                                        </div>
                                        <div class="prose prose-sm max-w-none prose-headings:text-gray-800 prose-p:text-gray-600">
                                            {!! Str::markdown($insights['detailed_analysis']) !!}
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- Fallback: Plain markdown rendering --}}
                                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                                    <div class="px-6 py-5 prose prose-sm max-w-none
                                                prose-headings:text-gray-800 prose-headings:font-semibold
                                                prose-p:text-gray-600 prose-strong:text-gray-700
                                                prose-table:text-sm prose-th:bg-gray-50 prose-th:px-3 prose-th:py-2
                                                prose-td:px-3 prose-td:py-2 prose-td:border-t
                                                prose-ul:text-gray-600 prose-ol:text-gray-600
                                                prose-a:text-indigo-600">
                                        {!! Str::markdown($responseText) !!}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Empty state --}}
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 flex flex-col items-center justify-center text-center">
                            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-1">Ready to Analyze</h3>
                            <p class="text-xs text-gray-400 max-w-sm">Select an analysis type and click "Run AI Analysis" to generate insights from your operational data.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    @else
        {{-- Saved Reports Tab --}}
        <div class="space-y-4">
            @forelse ($savedReports as $report)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-indigo-200 transition {{ $viewingLogId === $report->id ? 'ring-2 ring-indigo-500' : '' }}">
                    <div class="px-5 py-4 flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0 cursor-pointer" wire:click="loadReport({{ $report->id }})">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-sm font-semibold text-gray-800">
                                    {{ ucwords(str_replace('_', ' ', $report->analysis_type)) }}
                                </h4>
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-medium rounded-full">
                                    {{ \Carbon\Carbon::createFromFormat('!Y-m', $report->period)->format('M Y') }}
                                </span>
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-full">
                                    {{ $report->outlet?->name ?? 'All Outlets' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                <span>{{ $report->created_at->format('d M Y, h:i A') }}</span>
                                <span>&middot;</span>
                                <span>Powered by Servora AI</span>
                                @if ($report->requestedBy)
                                    <span>&middot;</span>
                                    <span>by {{ $report->requestedBy->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button wire:click="loadReport({{ $report->id }})"
                                    class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                View
                            </button>
                            <button wire:click="deleteReport({{ $report->id }})"
                                    wire:confirm="Delete this report? This cannot be undone."
                                    class="px-3 py-1.5 text-xs font-medium text-red-500 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                Delete
                            </button>
                        </div>
                    </div>
                    <div class="px-5 pb-4">
                        <p class="text-xs text-gray-400 line-clamp-2">{{ Str::limit(strip_tags($report->response_text), 200) }}</p>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">No Saved Reports</h3>
                    <p class="text-xs text-gray-400 max-w-sm">Generate your first AI analysis to see it saved here.</p>
                </div>
            @endforelse

            <div class="mt-2">{{ $savedReports->links() }}</div>
        </div>
    @endif
</div>
