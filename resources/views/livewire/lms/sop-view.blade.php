<div>
    {{-- Back --}}
    <div class="mb-6">
        <a href="{{ route('lms.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to all SOPs
        </a>
    </div>

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                @if ($recipe->category)
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full">{{ $recipe->category }}</span>
                @endif
                <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ $recipe->name }}</h1>
                @if ($recipe->code)
                    <p class="text-sm text-gray-400 mt-0.5">Code: {{ $recipe->code }}</p>
                @endif
                @if ($recipe->description)
                    <p class="text-sm text-gray-600 mt-2">{{ $recipe->description }}</p>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('lms.sop.pdf', $recipe->id) }}" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print / PDF
                </a>
            </div>
        </div>
    </div>

    {{-- Video --}}
    @php $embedUrl = $this->parseVideoEmbed($recipe->video_url); @endphp
    @if ($embedUrl)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Training Video</h2>
            </div>
            <div class="relative w-full" style="padding-bottom: 56.25%;">
                <iframe src="{{ $embedUrl }}" class="absolute inset-0 w-full h-full"
                        frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </div>
    @endif

    {{-- Cooking Steps --}}
    @if ($recipe->steps->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Cooking Steps</h2>
            <div class="space-y-4">
                @foreach ($recipe->steps as $step)
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                            {{ $step->sort_order + 1 }}
                        </div>
                        <div class="flex-1 pt-1">
                            @if ($step->title)
                                <h3 class="font-semibold text-gray-800 text-sm">{{ $step->title }}</h3>
                            @endif
                            <p class="text-sm text-gray-600 mt-0.5 whitespace-pre-line">{{ $step->instruction }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Ingredients --}}
    @if ($recipe->lines->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Ingredients</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Yield: {{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }}
                    {{ $recipe->yieldUom?->abbreviation }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Ingredient</th>
                            <th class="px-4 py-2 text-right">Quantity</th>
                            <th class="px-4 py-2 text-left">UOM</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($recipe->lines as $idx => $line)
                            <tr>
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $line->ingredient?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $line->uom?->abbreviation ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Plating Images --}}
    @if ($dineInImages->count() || $takeawayImages->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" x-data="{ tab: 'dine_in' }">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Plating Presentation</h2>

            @if ($dineInImages->count() && $takeawayImages->count())
                <div class="flex rounded-lg overflow-hidden border border-gray-200 mb-4 w-fit">
                    <button @click="tab = 'dine_in'"
                            :class="tab === 'dine_in' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-4 py-1.5 text-sm font-medium transition">
                        Dine-In
                    </button>
                    <button @click="tab = 'takeaway'"
                            :class="tab === 'takeaway' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-4 py-1.5 text-sm font-medium transition border-l border-gray-200">
                        Takeaway
                    </button>
                </div>
            @endif

            @if ($dineInImages->count())
                <div x-show="tab === 'dine_in'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($dineInImages as $img)
                        <div class="rounded-lg overflow-hidden border border-gray-200">
                            <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-48 object-cover" />
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($takeawayImages->count())
                <div x-show="tab === 'takeaway'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($takeawayImages as $img)
                        <div class="rounded-lg overflow-hidden border border-gray-200">
                            <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-48 object-cover" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
