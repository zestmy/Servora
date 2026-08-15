{{--
    What an auditor sees three seconds after scanning the QR on the paper.

    One card that answers one question — does this certificate stand — with
    the verdict as the loudest thing on the page. The details underneath are
    for cross-checking against the paper in their hand, so they mirror the
    certificate's own fields word for word. Standalone HTML like the video
    share page: a public visitor has no session, no bundle, no reason to wait
    for either.
--}}
@php
    $state = ! $certificate ? 'missing'
        : ($certificate->isRevoked() ? 'revoked'
        : ($certificate->isExpired() ? 'expired' : 'valid'));

    [$tint, $icon, $verdict, $meaning] = match ($state) {
        'valid'   => ['#0d9488', '✓', 'Valid certificate',   'This certificate is genuine and currently in force.'],
        'expired' => ['#b45309', '!', 'Expired certificate', 'This certificate is genuine but its validity period has ended.'],
        'revoked' => ['#b91c1c', '✕', 'Revoked certificate', 'This certificate was issued but has since been withdrawn by the issuer.'],
        'missing' => ['#475569', '?', 'Not found',           'No certificate matches this serial. Check the serial, or treat the document as unverified.'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Certificate verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9; color: #0f172a; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: #fff; border-radius: 16px; max-width: 420px; width: 100%;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); overflow: hidden;
        }
        .verdict { padding: 28px 24px; text-align: center; color: #fff; background: {{ $tint }}; }
        .verdict .mark {
            width: 52px; height: 52px; line-height: 52px; margin: 0 auto 10px auto;
            border-radius: 50%; background: rgba(255,255,255,0.22);
            font-size: 26px; font-weight: 700;
        }
        .verdict h1 { font-size: 20px; }
        .verdict p { margin-top: 6px; font-size: 13px; opacity: 0.92; }
        .details { padding: 20px 24px 8px 24px; }
        .row { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .row:last-child { border-bottom: 0; }
        .k { font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase; color: #64748b; }
        .v { margin-top: 2px; font-size: 15px; font-weight: 600; }
        .serial { font-family: 'Courier New', monospace; letter-spacing: 1px; font-size: 14px; }
        .foot { padding: 14px 24px 18px 24px; text-align: center; font-size: 11px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="verdict">
            <div class="mark">{{ $icon }}</div>
            <h1>{{ $verdict }}</h1>
            <p>{{ $meaning }}</p>
        </div>

        @if ($certificate)
            <div class="details">
                <div class="row">
                    <div class="k">Awarded to</div>
                    <div class="v">{{ $certificate->recipient_name }}</div>
                </div>
                <div class="row">
                    <div class="k">For completing</div>
                    <div class="v">{{ $certificate->title }}</div>
                </div>
                <div class="row">
                    <div class="k">Issued by</div>
                    <div class="v">{{ $certificate->company->brand_name ?? $certificate->company->name ?? '—' }}</div>
                </div>
                <div class="row">
                    <div class="k">Issued on</div>
                    <div class="v">{{ $certificate->issued_at?->format('j F Y') ?? '—' }}</div>
                </div>
                <div class="row">
                    <div class="k">{{ $certificate->isExpired() ? 'Expired on' : 'Valid until' }}</div>
                    <div class="v">{{ $certificate->expires_on?->format('j F Y') ?? 'No expiry' }}</div>
                </div>
                <div class="row">
                    <div class="k">Serial</div>
                    <div class="v serial">{{ $certificate->serial }}</div>
                </div>
            </div>
        @endif

        <div class="foot">Verified against the issuer's records by {{ config('app.name', 'Servora') }}.</div>
    </div>
</body>
</html>
