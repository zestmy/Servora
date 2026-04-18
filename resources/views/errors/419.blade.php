{{-- 419 Page Expired — rendered by Laravel when a CSRF token check fails.
     Instead of the default scary message, we tell the user their session
     refreshed and automatically send them back where they came from, which
     reloads the form with a fresh CSRF token. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Session refreshed</title>
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
            max-width: 420px;
            width: 100%;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.75rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        h1 { font-size: 1.125rem; margin: 0 0 0.5rem; color: #111827; }
        p { color: #6b7280; font-size: 0.875rem; line-height: 1.5; margin: 0 0 1rem; }
        .spinner {
            width: 32px; height: 32px;
            border: 3px solid #e5e7eb;
            border-top-color: #4f46e5;
            border-radius: 50%;
            margin: 0 auto 1rem;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        a.btn {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4f46e5;
            text-decoration: none;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
        }
        a.btn:hover { background: #eef2ff; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h1>Refreshing your session…</h1>
        <p>Your page sat idle for a while and the security token expired. One moment — we're taking you back.</p>
        <noscript>
            <p>JavaScript is disabled. <a class="btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}">Continue</a></p>
        </noscript>
    </div>

    <script>
        (function () {
            // Send the user back to where they came from (almost always the
            // form page), which re-renders @csrf with a fresh token. Fall back
            // to the site root if no referrer is available.
            var back = document.referrer && document.referrer !== window.location.href
                ? document.referrer
                : '/';
            setTimeout(function () { window.location.replace(back); }, 900);
        })();
    </script>
</body>
</html>
