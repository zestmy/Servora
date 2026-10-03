{{-- 419 Page Expired: rendered by Laravel when a CSRF token check fails,
     almost always because a form sat open long enough for the session to
     expire. Kitchen-themed like 403/404/500: a ringing kitchen timer and a
     joke. It still sends the user back where they came from, which re-renders
     the form with a fresh token, but after a short countdown long enough to
     read the page, with a button to go straight away. Inline-only, like the
     other error pages. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>419 &middot; Your Order Went Cold</title>
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
        .timer { transform-origin: 90px 84px; animation: ring 1.6s ease-in-out infinite; }
        @keyframes ring {
            0%, 55%, 100% { transform: rotate(0); }
            60% { transform: rotate(-9deg); }
            65% { transform: rotate(9deg); }
            70% { transform: rotate(-7deg); }
            75% { transform: rotate(7deg); }
            80% { transform: rotate(-4deg); }
            85% { transform: rotate(0); }
        }
        .hand { transform-origin: 90px 84px; animation: sweep 6s linear infinite; }
        @keyframes sweep { to { transform: rotate(360deg); } }
        .ding { opacity: 0; animation: ding 1.6s ease-out infinite; }
        @keyframes ding {
            0%, 55% { opacity: 0; transform: scale(0.8); }
            62%     { opacity: 1; transform: scale(1); }
            90%, 100% { opacity: 0; transform: scale(1.15); }
        }
        .ding { transform-box: fill-box; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            .timer, .hand, .ding { animation: none; }
            .ding { opacity: 1; }
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
        .countdown { margin-top: 1.25rem; font-size: 0.8125rem; color: #6b7280; }
        .bar { margin-top: 0.5rem; height: 4px; border-radius: 999px; background: #edf4ff; overflow: hidden; }
        .bar > span { display: block; height: 100%; width: 100%; background: #0962ef; transform-origin: left; }
        .bar.run > span { animation: drain var(--wait) linear forwards; }
        @keyframes drain { to { transform: scaleX(0); } }
    </style>
</head>
<body>
    <main class="card">
        <div class="scene" aria-hidden="true">
            <svg viewBox="0 0 180 150" fill="none" stroke-linecap="round" stroke-linejoin="round">
                {{-- "ding" marks --}}
                <g class="ding" stroke="#f59e0b" stroke-width="3">
                    <path d="M44 46 l-10 -8" />
                    <path d="M40 62 l-13 -1" />
                    <path d="M136 46 l10 -8" />
                    <path d="M140 62 l13 -1" />
                </g>
                {{-- kitchen timer --}}
                <g class="timer">
                    <rect x="84" y="30" width="12" height="10" rx="3" fill="#0748b3" />
                    <circle cx="90" cy="84" r="44" fill="#0962ef" />
                    <circle cx="90" cy="84" r="34" fill="white" />
                    <g stroke="#b7d2fe" stroke-width="3">
                        <path d="M90 54 v6" /><path d="M90 108 v6" />
                        <path d="M60 84 h6" /><path d="M114 84 h6" />
                    </g>
                    <path class="hand" d="M90 84 L90 60" stroke="#0748b3" stroke-width="4" />
                    <circle cx="90" cy="84" r="4" fill="#0748b3" />
                </g>
                <ellipse cx="90" cy="140" rx="46" ry="5" fill="#edf4ff" />
            </svg>
        </div>

        <p class="code">Error 419</p>
        <h1>Your order went cold</h1>
        <p>This page sat on the pass a little too long, so its security token expired. Nothing&rsquo;s wrong. We&rsquo;ll heat it up and take you back so you can try again.</p>

        <div class="joke" aria-live="polite">
            <div class="joke-q" id="joke-q">Why did the form expire?</div>
            <div class="joke-a" id="joke-a">It was left out longer than the food safety rules allow.</div>
        </div>

        <div class="actions">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn btn-primary" id="back">Take me back now</a>
        </div>
        <div class="countdown" id="countdown" hidden>
            <span id="countdown-text">Heading back in 5 seconds&hellip;</span>
            <div class="bar" id="bar"><span></span></div>
        </div>
    </main>

    <script>
        (function () {
            var jokes = [
                ['Why did the form expire?', 'It was left out longer than the food safety rules allow.'],
                ['What did the chef say about the stale page?', '\u201cSend it back. We\u2019ll make it fresh.\u201d'],
                ['Why did the session time out?', 'It got tired of waiting for its order.'],
                ['What\u2019s a form\u2019s least favourite word?', 'Expired. Right after \u201cbest before\u201d.'],
                ['Why was the teh tarik sent back?', 'Too long on the counter. Same as this page.'],
                ['How do you know a page has gone stale?', 'It starts asking for a fresh token.'],
                ['What did the kitchen timer say to the form?', '\u201cDing! You\u2019re done. Well, overdone.\u201d']
            ];
            var i = Math.floor(Math.random() * jokes.length);
            document.getElementById('joke-q').textContent = jokes[i][0];
            document.getElementById('joke-a').textContent = jokes[i][1];

            // Send the user back to where they came from (almost always the
            // form page), which re-renders @csrf with a fresh token. Fall back
            // to the site root if no referrer is available.
            var back = document.referrer && document.referrer !== window.location.href
                ? document.referrer
                : '/';
            var link = document.getElementById('back');
            link.href = back;

            var seconds = 5;
            var text = document.getElementById('countdown-text');
            var bar = document.getElementById('bar');
            document.getElementById('countdown').hidden = false;
            bar.style.setProperty('--wait', seconds + 's');
            bar.classList.add('run');

            var timer = setInterval(function () {
                seconds -= 1;
                if (seconds <= 0) {
                    clearInterval(timer);
                    text.textContent = 'Heading back\u2026';
                    window.location.replace(back);
                    return;
                }
                text.textContent = 'Heading back in ' + seconds + (seconds === 1 ? ' second\u2026' : ' seconds\u2026');
            }, 1000);

            link.addEventListener('click', function (e) {
                e.preventDefault();
                clearInterval(timer);
                window.location.replace(back);
            });
        })();
    </script>
</body>
</html>
