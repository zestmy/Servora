<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $recipe->name }} — {{ $company->brand_name ?? $company->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        .header { background: #1a1a2e; padding: 16px 20px; border-bottom: 1px solid #2a2a3e; }
        .brand { font-size: 13px; color: #8b8ba7; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .title { font-size: 18px; font-weight: 700; color: #fff; margin-top: 2px; }
        .video-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .video-container {
            position: relative;
            width: 100%; max-width: 960px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .video-container iframe { width: 100%; height: 100%; border: 0; }
        /* Hide YouTube logo / "Watch on YouTube" link area at bottom-right */
        .video-container::after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 120px; height: 40px;
            background: transparent;
            z-index: 10;
            pointer-events: auto;
            cursor: default;
        }
        .loading { text-align: center; color: #666; padding: 60px 20px; font-size: 14px; }
        .footer { padding: 12px 20px; text-align: center; font-size: 11px; color: #555; border-top: 1px solid #1a1a2e; }
        /* Prevent text selection and right-click context clues */
        .video-container { -webkit-user-select: none; user-select: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $company->brand_name ?? $company->name }}</div>
        <div class="title">{{ $recipe->name }}</div>
    </div>

    <div class="video-wrap">
        <div class="video-container" id="player-container">
            <div class="loading" id="loading-msg">Loading video...</div>
        </div>
    </div>

    <div class="footer">
        Powered by Servora
    </div>

    <script>
        // Video data loaded dynamically — not in page source as static HTML
        (function() {
            fetch('{{ route("video.share.data", $token) }}')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.embed) {
                        document.getElementById('loading-msg').textContent = 'Video not available.';
                        return;
                    }
                    var iframe = document.createElement('iframe');
                    iframe.src = data.embed;
                    iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');
                    iframe.setAttribute('allowfullscreen', '');
                    iframe.setAttribute('referrerpolicy', 'no-referrer');
                    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
                    document.getElementById('loading-msg').remove();
                    document.getElementById('player-container').appendChild(iframe);
                })
                .catch(function() {
                    document.getElementById('loading-msg').textContent = 'Failed to load video.';
                });
        })();
    </script>
</body>
</html>
