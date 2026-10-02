{{-- 500 Server Error: kitchen-themed, a pan on fire and a rotating joke, same
     shape as 403/404. This renders when the app itself has failed, so it must
     lean on as little of it as possible: inline styles and script, plain
     paths rather than url()/route(), and no auth, session or database calls.
     The only helper is now(), for the timestamp that helps find the entry in
     the error log. Errors go to the log only (no alerting service), so the
     copy does not promise anyone has been notified. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>500 &middot; Something Burned in the Kitchen</title>
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
        .pan { transform-origin: 90px 112px; animation: toss 2.6s ease-in-out infinite; }
        @keyframes toss {
            0%, 60%, 100% { transform: rotate(0); }
            70% { transform: rotate(-6deg) translateY(-3px); }
            80% { transform: rotate(3deg); }
            90% { transform: rotate(-2deg); }
        }
        .flame { transform-origin: 90px 96px; animation: flicker 0.9s ease-in-out infinite alternate; }
        .flame.inner { animation-duration: 0.6s; animation-delay: 0.2s; }
        @keyframes flicker {
            0%   { transform: scale(1, 1) skewX(0); }
            50%  { transform: scale(0.92, 1.08) skewX(-4deg); }
            100% { transform: scale(1.06, 0.94) skewX(4deg); }
        }
        .smoke circle { opacity: 0; animation: puff 3s ease-out infinite; }
        .smoke circle:nth-child(2) { animation-delay: 1s; }
        .smoke circle:nth-child(3) { animation-delay: 2s; }
        @keyframes puff {
            0%   { opacity: 0; transform: translateY(0) scale(0.6); }
            20%  { opacity: 0.55; }
            100% { opacity: 0; transform: translateY(-46px) scale(1.5); }
        }
        .smoke circle { transform-box: fill-box; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            .pan, .flame, .smoke circle { animation: none; }
            .smoke circle { opacity: 0.4; }
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
        button.btn { font: inherit; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: 0; }
        .stamp { margin-top: 1rem; font-size: 0.75rem; color: #6b7280; }
    </style>
</head>
<body>
    <main class="card">
        <div class="scene" aria-hidden="true">
            <svg viewBox="0 0 180 150" fill="none" stroke-linecap="round" stroke-linejoin="round">
                {{-- smoke --}}
                <g class="smoke" fill="#d1d5db">
                    <circle cx="80" cy="54" r="9" />
                    <circle cx="96" cy="50" r="11" />
                    <circle cx="88" cy="58" r="8" />
                </g>
                {{-- flames --}}
                <path class="flame" d="M90 64 C78 78 72 88 76 98 C79 106 101 106 104 98 C108 88 100 82 96 74 C95 82 92 84 90 84 C92 76 92 70 90 64 Z" fill="#f97316" />
                <path class="flame inner" d="M90 80 C84 88 82 94 84 99 C86 103 94 103 96 99 C98 94 95 90 93 86 C92 90 90 91 89 90 C90 87 91 84 90 80 Z" fill="#facc15" />
                {{-- pan --}}
                <g class="pan">
                    <path d="M40 104 h100 a6 6 0 0 1 -6 10 h-88 a6 6 0 0 1 -6 -10 z" fill="#0962ef" />
                    <rect x="40" y="100" width="100" height="6" rx="3" fill="#0748b3" />
                    <rect x="138" y="100" width="34" height="6" rx="3" fill="#374151" />
                </g>
                {{-- burner --}}
                <ellipse cx="90" cy="128" rx="58" ry="7" fill="#edf4ff" stroke="#b7d2fe" stroke-width="2" />
            </svg>
        </div>

        <p class="code">Error 500</p>
        <h1>Something burned in the kitchen</h1>
        <p>That one&rsquo;s on us, not you. Our side tripped over while cooking up this page. Give it a moment and try again.</p>

        <div class="joke" aria-live="polite">
            <div class="joke-q" id="joke-q">Why did the server go to cooking school?</div>
            <div class="joke-a" id="joke-a">It kept burning the requests.</div>
        </div>
        <button type="button" class="another" id="another" hidden>Serve another joke &rarr;</button>

        <div class="actions">
            <button type="button" class="btn btn-primary" id="retry">Try again</button>
            <a href="/dashboard" class="btn btn-ghost">Back to the dashboard</a>
        </div>
        <p class="stamp">
            If it keeps happening, tell your admin it happened at
            <strong>{{ now()->format('j M Y, g:i A') }}</strong> so they can find it in the error log.
        </p>
    </main>

    <script>
        (function () {
            var jokes = [
                ['Why did the server go to cooking school?', 'It kept burning the requests.'],
                ['What did the chef say when the page crashed?', '\u201cWell, that\u2019s a recipe for disaster.\u201d'],
                ['Why was the kitchen so hot?', 'The code was on fire, and not in the good way.'],
                ['What\u2019s a programmer\u2019s favourite kitchen tool?', 'The debugger. Also the fire extinguisher.'],
                ['Why did the sambal break the server?', 'Too much heat for one request.'],
                ['What did the wok say to the error?', '\u201cYou\u2019ve really stirred things up.\u201d'],
                ['Why did the cake fail to load?', 'It hadn\u2019t finished baking yet.'],
                ['How do chefs fix a crashed page?', 'They let it cool down, then plate it again.'],
                ['Why was the roti canai late?', 'The server was still flipping it.'],
                ['What do you call a kitchen with no errors?', 'A myth. Every kitchen has a bad night.']
            ];
            var q = document.getElementById('joke-q');
            var a = document.getElementById('joke-a');
            var btn = document.getElementById('another');
            var last = 0;

            function serve() {
                var i;
                do { i = Math.floor(Math.random() * jokes.length); } while (i === last);
                last = i;
                q.style.opacity = a.style.opacity = 0;
                setTimeout(function () {
                    q.textContent = jokes[i][0];
                    a.textContent = jokes[i][1];
                    q.style.opacity = a.style.opacity = 1;
                }, 200);
            }

            serve();
            btn.hidden = false;
            btn.addEventListener('click', serve);

            // Re-requesting the same URL is the useful retry: most 500s here
            // are a timeout or a deploy mid-swap and clear on their own.
            document.getElementById('retry').addEventListener('click', function () {
                location.reload();
            });
        })();
    </script>
</body>
</html>
