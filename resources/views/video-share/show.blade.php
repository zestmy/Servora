<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $recipe->name }} — {{ $company->brand_name ?? $company->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        .header { background: #1a1a2e; padding: 16px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #2a2a3e; }
        .brand { font-size: 13px; color: #8b8ba7; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .title { font-size: 18px; font-weight: 700; color: #fff; }
        .video-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .video-container { width: 100%; max-width: 960px; aspect-ratio: 16/9; background: #000; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .video-container iframe { width: 100%; height: 100%; border: 0; }
        .no-video { text-align: center; color: #666; padding: 60px 20px; }
        .no-video p { font-size: 16px; }
        .footer { padding: 12px 20px; text-align: center; font-size: 11px; color: #555; border-top: 1px solid #1a1a2e; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ $company->brand_name ?? $company->name }}</div>
            <div class="title">{{ $recipe->name }}</div>
        </div>
    </div>

    <div class="video-wrap">
        @if ($embedUrl)
            <div class="video-container">
                <iframe src="{{ $embedUrl }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            </div>
        @else
            <div class="no-video">
                <p>This video is not available.</p>
            </div>
        @endif
    </div>

    <div class="footer">
        Powered by Servora
    </div>
</body>
</html>
