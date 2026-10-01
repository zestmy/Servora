{{-- 404 Not Found — kitchen-themed, with an animated cloche and a rotating
     joke. Styles and script are inline (like 503/419) so the page renders
     even when the Vite manifest is missing or the request never reached the
     web middleware group. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>404 &middot; This Dish Isn&rsquo;t on the Menu</title>
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
        .cloche {
            transform-origin: 90px 108px;
            animation: lift 4s ease-in-out infinite;
        }
        @keyframes lift {
            0%, 30%   { transform: translateY(0) rotate(0); }
            42%, 72%  { transform: translateY(-34px) rotate(-12deg); }
            84%, 100% { transform: translateY(0) rotate(0); }
        }
        .question {
            opacity: 0;
            animation: peek 4s ease-in-out infinite;
        }
        @keyframes peek {
            0%, 36%  { opacity: 0; transform: translateY(6px); }
            46%, 68% { opacity: 1; transform: translateY(0); }
            78%, 100% { opacity: 0; transform: translateY(6px); }
        }
        .steam path {
            stroke-dasharray: 30;
            stroke-dashoffset: 30;
            animation: steam 4s ease-in-out infinite;
        }
        .steam path:nth-child(2) { animation-delay: 0.15s; }
        .steam path:nth-child(3) { animation-delay: 0.3s; }
        @keyframes steam {
            0%, 38%  { stroke-dashoffset: 30; opacity: 0; }
            50%      { stroke-dashoffset: 0; opacity: 0.8; }
            70%      { stroke-dashoffset: -30; opacity: 0; }
            100%     { stroke-dashoffset: -30; opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .cloche, .question, .steam path { animation: none; }
            .cloche { transform: translateY(-34px) rotate(-12deg); }
            .question { opacity: 1; }
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
    </style>
</head>
<body>
    <main class="card">
        <div class="scene" aria-hidden="true">
            <svg viewBox="0 0 180 150" fill="none" stroke-linecap="round" stroke-linejoin="round">
                {{-- plate --}}
                <ellipse cx="90" cy="118" rx="78" ry="14" fill="#edf4ff" stroke="#b7d2fe" stroke-width="2" />
                <ellipse cx="90" cy="115" rx="52" ry="8" fill="white" stroke="#b7d2fe" stroke-width="1.5" />
                {{-- the empty dish: a question mark --}}
                <text class="question" x="90" y="110" text-anchor="middle"
                      font-size="34" font-weight="700" fill="#0962ef"
                      font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">?</text>
                {{-- steam --}}
                <g class="steam" stroke="#9ca3af" stroke-width="2">
                    <path d="M74 80 q-6 -8 0 -16 q6 -8 0 -16" />
                    <path d="M90 76 q-6 -8 0 -16 q6 -8 0 -16" />
                    <path d="M106 80 q-6 -8 0 -16 q6 -8 0 -16" />
                </g>
                {{-- cloche --}}
                <g class="cloche">
                    <path d="M28 108 a62 58 0 0 1 124 0 z" fill="#0962ef" />
                    <path d="M44 92 a48 44 0 0 1 30 -30" stroke="white" stroke-width="4" opacity="0.35" />
                    <rect x="22" y="104" width="136" height="7" rx="3.5" fill="#0748b3" />
                    <circle cx="90" cy="46" r="7" fill="#0748b3" />
                </g>
            </svg>
        </div>

        <p class="code">Error 404</p>
        <h1>This dish isn&rsquo;t on the menu</h1>
        <p>We lifted the lid and&hellip; nothing. The page you ordered was 86&rsquo;d, moved, or never left the kitchen.</p>

        <div class="joke" aria-live="polite">
            <div class="joke-q" id="joke-q">Why did the page go to the walk-in freezer?</div>
            <div class="joke-a" id="joke-a">It needed to chill after being 404&rsquo;d.</div>
        </div>
        <button type="button" class="another" id="another" hidden>Serve another joke &rarr;</button>

        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Back to the main page</a>
            <a href="{{ url('/dashboard') }}" class="btn btn-ghost" id="back">Go to dashboard</a>
        </div>
    </main>

    <script>
        (function () {
            var jokes = [
                ['Why did the page go to the walk-in freezer?', 'It needed to chill after being 404’d.'],
                ['Why did the chef stop looking for this page?', 'There was no thyme left.'],
                ['What did the waiter say about this URL?', '“Sorry, the kitchen says we’re all out.”'],
                ['Why don’t eggs tell jokes about missing pages?', 'They’d crack each other up — and still not find it.'],
                ['What do you call a page that ran off with the cheese?', 'Nacho page.'],
                ['Why was the soup so sad?', 'It was looking for a page that was never stocked.'],
                ['How does the head chef handle a missing order?', 'Whisks it off and starts from scratch.'],
                ['Why did the cookie go to the doctor?', 'It was feeling crumby — just like this URL.'],
                ['What did the lettuce say to the broken link?', '“Lettuce take you somewhere better.”'],
                ['Why did the tomato turn red?', 'It saw this page wasn’t dressed yet.'],
                ['What’s a server’s least favourite table?', 'Table 404 — nobody’s ever there.'],
                ['Why did the rice refuse to look for the page?', 'It was too basmati to care.']
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

            // Offer a real "go back" when the visitor came from another page
            // on this site; otherwise keep the dashboard link.
            var back = document.getElementById('back');
            if (document.referrer && document.referrer.indexOf(location.origin) === 0
                && document.referrer !== location.href) {
                back.textContent = '← Go back';
                back.href = document.referrer;
            }
        })();
    </script>
</body>
</html>
