{{-- 503 Service Unavailable: shown while the app is in maintenance mode
     (php artisan down during deploys). Pre-rendered by `artisan down
     --render="errors::503"`, so it is served from a static snapshot while
     composer/npm are replacing the very assets a @vite build would need.
     Everything must therefore be inline: no external CSS, JS, or images,
     and nothing that would freeze at snapshot time (no timestamps).
     Kitchen-themed like 403/404/419/500: a pot simmering with its lid
     rattling, and a joke that changes on every 15 second refresh. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="refresh" content="15" />
    <meta name="robots" content="noindex" />
    <title>We&rsquo;re 86&rsquo;d for a Moment</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f9fafb;
            color: #111827;
            padding: 1.5rem;
        }
        .card {
            max-width: 440px;
            width: 100%;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem 1.5rem 1.75rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .scene { width: 180px; height: 150px; margin: 0 auto 0.5rem; }
        .scene svg { width: 100%; height: 100%; overflow: visible; }
        .lid { transform-origin: 90px 66px; animation: rattle 1.8s ease-in-out infinite; }
        @keyframes rattle {
            0%, 50%, 100% { transform: translateY(0) rotate(0); }
            58% { transform: translateY(-5px) rotate(-4deg); }
            66% { transform: translateY(0) rotate(0); }
            74% { transform: translateY(-4px) rotate(3deg); }
            82% { transform: translateY(0) rotate(0); }
        }
        .spoon { transform-origin: 90px 96px; animation: stir 2.4s ease-in-out infinite; }
        @keyframes stir {
            0%, 100% { transform: rotate(-14deg); }
            50%      { transform: rotate(14deg); }
        }
        .bubbles circle { opacity: 0; transform-box: fill-box; transform-origin: center; animation: bubble 2.2s ease-out infinite; }
        .bubbles circle:nth-child(2) { animation-delay: 0.7s; }
        .bubbles circle:nth-child(3) { animation-delay: 1.4s; }
        @keyframes bubble {
            0%   { opacity: 0; transform: translateY(0) scale(0.5); }
            25%  { opacity: 0.7; }
            100% { opacity: 0; transform: translateY(-34px) scale(1.2); }
        }
        @media (prefers-reduced-motion: reduce) {
            .lid, .spoon, .bubbles circle { animation: none; }
            .bubbles circle { opacity: 0.5; }
        }
        .code {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #0962ef;
            margin: 0 0 0.375rem;
        }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; color: #111827; }
        p { color: #6b7280; font-size: 0.875rem; line-height: 1.5; margin: 0; }
        .joke {
            margin: 1.25rem 0 0;
            padding: 0.875rem 1rem;
            background: #edf4ff;
            border-radius: 10px;
            text-align: left;
            min-height: 4.5rem;
        }
        .joke-q { color: #111827; font-weight: 500; }
        .joke-a { color: #0748b3; margin-top: 0.25rem; }
        .joke-a, .joke-q { transition: opacity 0.2s; }
        .another {
            margin-top: 0.5rem;
            background: none;
            border: 0;
            padding: 0.25rem;
            font: inherit;
            font-size: 0.8125rem;
            color: #0962ef;
            cursor: pointer;
        }
        .another:hover { text-decoration: underline; }
        .actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.25rem;
        }
        .btn {
            display: inline-block;
            padding: 0.625rem 1.125rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-primary { background: #0962ef; color: white; }
        .btn-primary:hover { background: #0748b3; }
        .btn-ghost { color: #0962ef; border: 1px solid #b7d2fe; }
        .btn-ghost:hover { background: #edf4ff; }
        .hint { margin-top: 1.25rem; font-size: 0.8125rem; color: #6b7280; }
    </style>
</head>
<body>
    <main class="card">
        <div class="scene" aria-hidden="true">
            <svg viewBox="0 0 180 150" fill="none" stroke-linecap="round" stroke-linejoin="round">
                {{-- steam bubbles --}}
                <g class="bubbles" fill="#d1d5db">
                    <circle cx="70" cy="52" r="5" />
                    <circle cx="92" cy="46" r="6" />
                    <circle cx="112" cy="52" r="4.5" />
                </g>
                {{-- spoon, leaning out of the pot --}}
                <g class="spoon">
                    <path d="M90 96 L122 34" stroke="#9ca3af" stroke-width="5" />
                    <ellipse cx="124" cy="30" rx="7" ry="5" fill="#9ca3af" transform="rotate(-62 124 30)" />
                </g>
                {{-- pot --}}
                <path d="M40 72 h100 v44 a14 14 0 0 1 -14 14 h-72 a14 14 0 0 1 -14 -14 z" fill="#0962ef" />
                <rect x="26" y="80" width="18" height="7" rx="3.5" fill="#0748b3" />
                <rect x="136" y="80" width="18" height="7" rx="3.5" fill="#0748b3" />
                <path d="M52 92 h76" stroke="white" stroke-width="3" opacity="0.25" />
                {{-- lid --}}
                <g class="lid">
                    <path d="M36 70 a54 14 0 0 1 108 0 z" fill="#0748b3" />
                    <rect x="84" y="50" width="12" height="7" rx="3.5" fill="#374151" />
                </g>
                <ellipse cx="90" cy="140" rx="58" ry="5" fill="#edf4ff" />
            </svg>
        </div>

        <p class="code">Maintenance</p>
        <h1>We&rsquo;re 86&rsquo;d for a moment</h1>
        <p>Servora is off the menu briefly while we plate up some new features. We&rsquo;ll be back on the pass shortly. Anything you&rsquo;ve already saved is safe.</p>

        <div class="joke" aria-live="polite">
            <div class="joke-q" id="joke-q">Why did the app close the kitchen?</div>
            <div class="joke-a" id="joke-a">Deep clean. The new features don&rsquo;t cook themselves.</div>
        </div>

        <p class="hint">
            This page checks again on its own
            <span id="countdown">every 15 seconds</span>.
            No need to refresh.
        </p>
    </main>

    <script>
        (function () {
            var jokes = [
                ['Why did the app close the kitchen?', 'Deep clean. The new features don\u2019t cook themselves.'],
                ['What\u2019s the server doing right now?', 'Mise en place. Everything in its place, then service.'],
                ['Why can\u2019t you come in yet?', 'The chef is still tasting the new recipe.'],
                ['What did the nasi lemak say to the update?', '\u201cTake your time, I\u2019m worth the wait.\u201d'],
                ['How long does an update take?', 'About as long as the rice. Don\u2019t lift the lid.'],
                ['Why is the menu board blank?', 'We\u2019re writing the specials. They\u2019re good ones.'],
                ['What do chefs do between services?', 'Restock, sharpen, update the app. In that order.'],
                ['Why did the soup ask for a moment?', 'It needed time to simmer down.']
            ];
            var i = Math.floor(Math.random() * jokes.length);
            document.getElementById('joke-q').textContent = jokes[i][0];
            document.getElementById('joke-a').textContent = jokes[i][1];

            // The meta refresh above does the reloading; this only shows how
            // long is left so the wait does not feel stuck.
            var left = 15;
            var el = document.getElementById('countdown');
            function show() {
                el.textContent = 'in ' + left + (left === 1 ? ' second' : ' seconds');
            }
            show();
            setInterval(function () {
                if (left > 1) { left -= 1; show(); }
            }, 1000);
        })();
    </script>
</body>
</html>
