<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $recipe->name }} — {{ $company->brand_name ?? $company->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #fff; min-height: 100vh; display: flex; flex-direction: column; -webkit-user-select: none; user-select: none; }
        .header { background: #1a1a2e; padding: 16px 20px; border-bottom: 1px solid #2a2a3e; }
        .brand { font-size: 13px; color: #8b8ba7; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .title { font-size: 18px; font-weight: 700; color: #fff; margin-top: 2px; }
        .video-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .player-outer {
            position: relative;
            width: 100%; max-width: 960px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        #yt-player { width: 100%; height: 100%; }
        /* Transparent overlay — blocks all YouTube UI interaction */
        .player-overlay {
            position: absolute; inset: 0; z-index: 10;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .play-icon {
            width: 72px; height: 72px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
            pointer-events: none;
        }
        .play-icon svg { width: 32px; height: 32px; fill: #fff; }
        .player-overlay:hover .play-icon,
        .player-overlay.show-icon .play-icon { opacity: 1; }
        /* Progress bar at bottom */
        .progress-bar {
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 4px; background: rgba(255,255,255,0.15); z-index: 11;
            cursor: pointer;
        }
        .progress-fill { height: 100%; background: #6366f1; width: 0%; transition: width 0.3s linear; }
        .progress-bar:hover { height: 6px; }
        .loading-msg { text-align: center; color: #666; padding: 60px 20px; font-size: 14px; }
        .footer { padding: 12px 20px; text-align: center; font-size: 11px; color: #555; border-top: 1px solid #1a1a2e; }
        /* Fullscreen button */
        .fs-btn {
            position: absolute; bottom: 12px; right: 12px; z-index: 12;
            background: rgba(0,0,0,0.5); border: none; color: #fff;
            width: 36px; height: 36px; border-radius: 6px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
        }
        .player-outer:hover .fs-btn { opacity: 0.8; }
        .fs-btn:hover { opacity: 1 !important; background: rgba(99,102,241,0.7); }
        .fs-btn svg { width: 18px; height: 18px; }
        /* Vimeo fallback — regular iframe with overlay */
        .vimeo-frame { width: 100%; height: 100%; border: 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $company->brand_name ?? $company->name }}</div>
        <div class="title">{{ $recipe->name }}</div>
    </div>

    <div class="video-wrap">
        <div class="player-outer" id="player-outer">
            <div class="loading-msg" id="loading-msg">Loading video...</div>
        </div>
    </div>

    <div class="footer">
        Powered by Servora
    </div>

    <script>
    (function() {
        var outer = document.getElementById('player-outer');
        var loadingMsg = document.getElementById('loading-msg');
        var ytPlayer = null;
        var isPlaying = false;
        var progressInterval = null;

        fetch('{{ route("video.share.data", $token) }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.type || !data.id) {
                    loadingMsg.textContent = 'Video not available.';
                    return;
                }

                if (data.type === 'youtube') {
                    initYouTube(data.id);
                } else if (data.type === 'vimeo') {
                    initVimeo(data.id);
                }
            })
            .catch(function() {
                loadingMsg.textContent = 'Failed to load video.';
            });

        function initYouTube(videoId) {
            // Load YouTube IFrame API
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(tag);

            // Create the target div before API loads
            loadingMsg.remove();
            var el = document.createElement('div');
            el.id = 'yt-player-el';
            outer.appendChild(el);

            window.onYouTubeIframeAPIReady = function() {
                ytPlayer = new YT.Player('yt-player-el', {
                    width: '100%',
                    height: '100%',
                    videoId: videoId,
                    playerVars: {
                        rel: 0,
                        modestbranding: 1,
                        controls: 0,
                        iv_load_policy: 3,
                        disablekb: 1,
                        playsinline: 1,
                        showinfo: 0,
                        fs: 1,
                        cc_load_policy: 0,
                        origin: window.location.origin
                    },
                    events: {
                        onReady: function(e) {
                            // Ensure iframe has allowfullscreen for our custom fullscreen button
                            var iframe = e.target.getIframe();
                            if (iframe) {
                                iframe.setAttribute('allowfullscreen', '');
                                iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');
                            }
                            addControls();
                        },
                        onStateChange: function(e) {
                            isPlaying = (e.data === YT.PlayerState.PLAYING);
                            updateIcon();
                            if (isPlaying) startProgress(); else stopProgress();
                        }
                    }
                });
            };
        }

        function initVimeo(videoId) {
            loadingMsg.remove();

            // Vimeo — use iframe with overlay (no JS API needed)
            var iframe = document.createElement('iframe');
            iframe.className = 'vimeo-frame';
            iframe.src = 'https://player.vimeo.com/video/' + videoId + '?dnt=1&title=0&byline=0&portrait=0&controls=0&autoplay=0';
            iframe.setAttribute('referrerpolicy', 'no-referrer');
            iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
            outer.insertBefore(iframe, outer.firstChild);

            // Simple overlay — click to message (Vimeo API requires SDK for control)
            var overlay = document.createElement('div');
            overlay.className = 'player-overlay';
            overlay.innerHTML = '<div class="play-icon"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>';
            overlay.addEventListener('click', function() {
                // Remove overlay to let user interact with Vimeo controls
                iframe.src = iframe.src.replace('controls=0', 'controls=1').replace('autoplay=0', 'autoplay=1');
                overlay.remove();
            });
            outer.appendChild(overlay);
            addFullscreenBtn();
        }

        function addControls() {
            // Overlay for play/pause
            var overlay = document.createElement('div');
            overlay.className = 'player-overlay';
            overlay.id = 'overlay';
            overlay.innerHTML = '<div class="play-icon" id="play-icon"><svg id="play-svg" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>';

            var clickTimeout = null;
            overlay.addEventListener('click', function(e) {
                e.preventDefault();
                if (clickTimeout) { clearTimeout(clickTimeout); clickTimeout = null; toggleFullscreen(); return; }
                clickTimeout = setTimeout(function() { clickTimeout = null; togglePlay(); }, 250);
            });
            outer.appendChild(overlay);

            // Progress bar
            var bar = document.createElement('div');
            bar.className = 'progress-bar';
            bar.innerHTML = '<div class="progress-fill" id="progress-fill"></div>';
            bar.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!ytPlayer) return;
                var pct = e.offsetX / bar.offsetWidth;
                ytPlayer.seekTo(ytPlayer.getDuration() * pct, true);
            });
            outer.appendChild(bar);

            addFullscreenBtn();
        }

        function addFullscreenBtn() {
            var btn = document.createElement('button');
            btn.className = 'fs-btn';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/></svg>';
            btn.addEventListener('click', function(e) { e.stopPropagation(); toggleFullscreen(); });
            outer.appendChild(btn);
        }

        function togglePlay() {
            if (!ytPlayer) return;
            if (isPlaying) { ytPlayer.pauseVideo(); } else { ytPlayer.playVideo(); }
        }

        function updateIcon() {
            var icon = document.getElementById('play-icon');
            var svg = document.getElementById('play-svg');
            if (!icon || !svg) return;
            if (isPlaying) {
                svg.innerHTML = '<path d="M6 4h4v16H6zM14 4h4v16h-4z"/>';
            } else {
                svg.innerHTML = '<path d="M8 5v14l11-7z"/>';
            }
            // Flash icon briefly
            var overlay = document.getElementById('overlay');
            if (overlay) {
                overlay.classList.add('show-icon');
                setTimeout(function() { overlay.classList.remove('show-icon'); }, 600);
            }
        }

        function startProgress() {
            stopProgress();
            progressInterval = setInterval(function() {
                if (!ytPlayer || !ytPlayer.getDuration) return;
                var pct = (ytPlayer.getCurrentTime() / ytPlayer.getDuration()) * 100;
                var fill = document.getElementById('progress-fill');
                if (fill) fill.style.width = pct + '%';
            }, 500);
        }

        function stopProgress() {
            if (progressInterval) { clearInterval(progressInterval); progressInterval = null; }
        }

        function goFullscreen(el) {
            if (el.requestFullscreen) return el.requestFullscreen();
            if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
            if (el.webkitEnterFullscreen) return el.webkitEnterFullscreen(); // iOS video
            if (el.msRequestFullscreen) return el.msRequestFullscreen();
            return Promise.reject();
        }

        function exitFullscreen() {
            if (document.exitFullscreen) return document.exitFullscreen();
            if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
            if (document.msExitFullscreen) return document.msExitFullscreen();
        }

        function toggleFullscreen() {
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                exitFullscreen();
                return;
            }

            var iframe = ytPlayer ? ytPlayer.getIframe() : outer.querySelector('iframe');

            // Try iframe → outer → video-wrap → body, first one that works
            var targets = [iframe, outer, document.querySelector('.video-wrap')].filter(Boolean);

            function tryNext(i) {
                if (i >= targets.length) return;
                try {
                    var result = goFullscreen(targets[i]);
                    if (result && result.catch) {
                        result.catch(function() { tryNext(i + 1); });
                    }
                } catch(e) {
                    tryNext(i + 1);
                }
            }
            tryNext(0);
        }
    })();
    </script>
</body>
</html>
