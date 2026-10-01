{{-- 403 Forbidden — kitchen-themed, a swinging STAFF ONLY sign and a
     rotating joke, same shape as 404. Inline styles and script so it renders
     whatever state the request was in when it was refused. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>403 &middot; Kitchen Crew Only</title>
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
        .sign {
            transform-origin: 90px 14px;
            animation: swing 3.2s ease-in-out infinite;
        }
        @keyframes swing {
            0%, 100% { transform: rotate(7deg); }
            50%      { transform: rotate(-7deg); }
        }
        .knob { animation: jiggle 3.2s ease-in-out infinite; transform-origin: 152px 118px; }
        @keyframes jiggle {
            0%, 70%, 100% { transform: rotate(0); }
            76% { transform: rotate(-35deg); }
            82% { transform: rotate(0); }
            88% { transform: rotate(-35deg); }
            94% { transform: rotate(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sign, .knob { animation: none; }
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
                {{-- swinging door --}}
                <rect x="30" y="20" width="132" height="128" rx="6" fill="#edf4ff" stroke="#b7d2fe" stroke-width="2" />
                <circle cx="96" cy="112" r="14" fill="white" stroke="#b7d2fe" stroke-width="2" />
                <g class="knob">
                    <rect x="140" y="114" width="20" height="7" rx="3.5" fill="#0748b3" />
                </g>
                {{-- hanging sign --}}
                <g class="sign">
                    <path d="M90 6 L62 40 M90 6 L118 40" stroke="#6b7280" stroke-width="1.5" />
                    <circle cx="90" cy="6" r="3" fill="#6b7280" />
                    <rect x="46" y="38" width="88" height="42" rx="6" fill="#0962ef" />
                    <rect x="50" y="42" width="80" height="34" rx="4" fill="none" stroke="white" stroke-width="1.5" opacity="0.5" />
                    <text x="90" y="56" text-anchor="middle" font-size="11" font-weight="700" fill="white" letter-spacing="1.5"
                          font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">STAFF</text>
                    <text x="90" y="70" text-anchor="middle" font-size="11" font-weight="700" fill="white" letter-spacing="1.5"
                          font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">ONLY</text>
                </g>
            </svg>
        </div>

        <p class="code">Error 403</p>
        <h1>Kitchen crew only past this door</h1>
        <p>Your apron doesn&rsquo;t have the right badge for this station. If you think it should, ask your manager or admin to give you access.</p>

        <div class="joke" aria-live="polite">
            <div class="joke-q" id="joke-q">Why can&rsquo;t you get into this page?</div>
            <div class="joke-a" id="joke-a">The head chef said it&rsquo;s a closed kitchen.</div>
        </div>
        <button type="button" class="another" id="another" hidden>Serve another joke &rarr;</button>

        <div class="actions">
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">Back to the dashboard</a>
            <a href="{{ url('/') }}" class="btn btn-ghost" id="back">Main page</a>
        </div>
    </main>

    <script>
        (function () {
            var jokes = [
                ['Why can\u2019t you get into this page?', 'The head chef said it\u2019s a closed kitchen.'],
                ['What did the bouncer say to the baguette?', '\u201cSorry, you\u2019re not on the bread list.\u201d'],
                ['Why was the cucumber turned away?', 'It tried to play it cool. Security wasn\u2019t fooled.'],
                ['What\u2019s the secret ingredient on this page?', 'It\u2019s a secret. That\u2019s the whole point.'],
                ['Why did the onion get stopped at the door?', 'Too many layers of clearance needed.'],
                ['What do you call a locked walk-in?', 'A cold reception.'],
                ['Why did the pastry chef guard this page?', 'Some recipes are on a knead-to-know basis.'],
                ['Why can\u2019t the new hire see this?', 'They\u2019re still on dishwashing duty.'],
                ['What did the sous-chef say at the pass?', '\u201cHands off, that ticket\u2019s not yours.\u201d'],
                ['Why was the egg denied entry?', 'It couldn\u2019t crack the password.']
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

            // Offer "go back" when the visitor came from another page on this
            // site, since that page is the one they do have access to.
            var back = document.getElementById('back');
            if (document.referrer && document.referrer.indexOf(location.origin) === 0
                && document.referrer !== location.href) {
                back.textContent = '\u2190 Go back';
                back.href = document.referrer;
            }
        })();
    </script>
</body>
</html>
