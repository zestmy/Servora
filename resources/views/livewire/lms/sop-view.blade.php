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
    @php
        $videoData = $this->getVideoData($recipe->video_url);
    @endphp
    @if ($videoData)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Training Video</h2>
            </div>
            <div class="relative w-full" style="padding-bottom: 56.25%; background: #000;" id="lms-player-outer">
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#666;font-size:14px;" id="lms-loading">Loading video...</div>
            </div>
        </div>

        <style>
            #lms-player-outer iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
            .lms-overlay { position: absolute; inset: 0; z-index: 10; cursor: pointer; display: flex; align-items: center; justify-content: center; }
            .lms-play-icon { width: 64px; height: 64px; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; pointer-events: none; }
            .lms-play-icon svg { width: 28px; height: 28px; fill: #fff; }
            .lms-overlay:hover .lms-play-icon, .lms-overlay.show-icon .lms-play-icon { opacity: 1; }
            .lms-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(255,255,255,0.2); z-index: 11; cursor: pointer; }
            .lms-progress:hover { height: 6px; }
            .lms-progress-fill { height: 100%; background: #6366f1; width: 0%; transition: width 0.3s linear; }
        </style>

        @script
        <script>
            (function() {
                var outer = document.getElementById('lms-player-outer');
                var loading = document.getElementById('lms-loading');
                var videoType = @js($videoData['type']);
                var videoId = @js($videoData['id']);
                var ytPlayer = null;
                var isPlaying = false;
                var progressInterval = null;

                if (videoType === 'youtube') {
                    initYouTube();
                } else if (videoType === 'vimeo') {
                    initVimeo();
                }

                function initYouTube() {
                    loading.remove();
                    var el = document.createElement('div');
                    el.id = 'lms-yt-el';
                    outer.appendChild(el);

                    var tag = document.createElement('script');
                    tag.src = 'https://www.youtube.com/iframe_api';
                    document.head.appendChild(tag);

                    window.onYouTubeIframeAPIReady = function() {
                        ytPlayer = new YT.Player('lms-yt-el', {
                            width: '100%', height: '100%',
                            videoId: videoId,
                            playerVars: { rel: 0, modestbranding: 1, controls: 0, iv_load_policy: 3, disablekb: 1, playsinline: 1, showinfo: 0, fs: 0, cc_load_policy: 0 },
                            events: {
                                onReady: function() { addControls(); },
                                onStateChange: function(e) {
                                    isPlaying = (e.data === YT.PlayerState.PLAYING);
                                    updateIcon();
                                    if (isPlaying) startProgress(); else stopProgress();
                                }
                            }
                        });
                    };
                }

                function initVimeo() {
                    loading.remove();
                    var iframe = document.createElement('iframe');
                    iframe.src = 'https://player.vimeo.com/video/' + videoId + '?dnt=1&title=0&byline=0&portrait=0&controls=1';
                    iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;';
                    outer.appendChild(iframe);
                }

                function addControls() {
                    var overlay = document.createElement('div');
                    overlay.className = 'lms-overlay';
                    overlay.id = 'lms-overlay';
                    overlay.innerHTML = '<div class="lms-play-icon" id="lms-play-icon"><svg id="lms-play-svg" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>';
                    overlay.addEventListener('click', function(e) { e.preventDefault(); togglePlay(); });
                    outer.appendChild(overlay);

                    var bar = document.createElement('div');
                    bar.className = 'lms-progress';
                    bar.innerHTML = '<div class="lms-progress-fill" id="lms-progress-fill"></div>';
                    bar.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (!ytPlayer) return;
                        var rect = bar.getBoundingClientRect();
                        ytPlayer.seekTo(ytPlayer.getDuration() * ((e.clientX - rect.left) / rect.width), true);
                    });
                    outer.appendChild(bar);
                }

                function togglePlay() {
                    if (!ytPlayer) return;
                    if (isPlaying) ytPlayer.pauseVideo(); else ytPlayer.playVideo();
                }

                function updateIcon() {
                    var svg = document.getElementById('lms-play-svg');
                    if (!svg) return;
                    svg.innerHTML = isPlaying ? '<path d="M6 4h4v16H6zM14 4h4v16h-4z"/>' : '<path d="M8 5v14l11-7z"/>';
                    var overlay = document.getElementById('lms-overlay');
                    if (overlay) { overlay.classList.add('show-icon'); setTimeout(function() { overlay.classList.remove('show-icon'); }, 600); }
                }

                function startProgress() {
                    stopProgress();
                    progressInterval = setInterval(function() {
                        if (!ytPlayer || !ytPlayer.getDuration) return;
                        var fill = document.getElementById('lms-progress-fill');
                        if (fill) fill.style.width = ((ytPlayer.getCurrentTime() / ytPlayer.getDuration()) * 100) + '%';
                    }, 500);
                }

                function stopProgress() { if (progressInterval) { clearInterval(progressInterval); progressInterval = null; } }
            })();
        </script>
        @endscript
    @endif

    {{-- Preparation Steps --}}
    @if ($recipe->steps->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Preparation Steps</h2>
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
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6"
             x-data="{ tab: 'dine_in', lightbox: false, lightboxSrc: '', lightboxAlt: '' }">
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
                        <div class="rounded-lg overflow-hidden border border-gray-200 cursor-pointer hover:shadow-md hover:border-indigo-300 transition"
                             @click="lightboxSrc = '{{ $img->url() }}'; lightboxAlt = '{{ $img->file_name }}'; lightbox = true">
                            <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-48 object-cover" />
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($takeawayImages->count())
                <div x-show="tab === 'takeaway'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($takeawayImages as $img)
                        <div class="rounded-lg overflow-hidden border border-gray-200 cursor-pointer hover:shadow-md hover:border-indigo-300 transition"
                             @click="lightboxSrc = '{{ $img->url() }}'; lightboxAlt = '{{ $img->file_name }}'; lightbox = true">
                            <img src="{{ $img->url() }}" alt="{{ $img->file_name }}" class="w-full h-48 object-cover" />
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Lightbox Modal --}}
            <div x-show="lightbox" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="lightbox = false"
                 @keydown.escape.window="lightbox = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                <div @click.stop class="relative max-w-4xl max-h-[90vh] w-full">
                    <button @click="lightbox = false"
                            class="absolute -top-10 right-0 text-white hover:text-gray-300 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <img :src="lightboxSrc" :alt="lightboxAlt"
                         class="w-full h-auto max-h-[85vh] object-contain rounded-lg shadow-2xl" />
                </div>
            </div>
        </div>
    @endif
</div>
