<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            color: #1f2937;
            line-height: 1.45;
        }
        .container { padding: 20px 25px 55px; }

        /* ── Compact header (like reference SOP) ──────────── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .header-logo {
            display: table-cell;
            width: 90px;
            vertical-align: middle;
            text-align: center;
            padding: 6px;
            border-right: 1px solid #000;
        }
        .header-logo img { max-height: 60px; max-width: 80px; }
        .header-title {
            display: table-cell;
            vertical-align: top;
            padding: 0;
            border-right: 1px solid #000;
        }
        .header-title .recipe-title-main {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            padding: 6px 10px 2px;
            letter-spacing: 0.3px;
        }
        .header-title .recipe-title-sub {
            font-size: 11pt;
            color: #333;
            font-style: italic;
            padding: 0 10px 4px;
            border-bottom: 1px solid #ccc;
        }
        .header-title .meta-row {
            display: table;
            width: 100%;
            background: #f3f4f6;
        }
        .header-title .meta-cell {
            display: table-cell;
            padding: 3px 10px;
            font-size: 10pt;
            color: #111;
            border-top: 1px solid #e5e7eb;
        }
        .header-title .meta-cell strong { font-weight: bold; }
        .header-title .dept-row {
            display: table;
            width: 100%;
            border-top: 1px solid #000;
        }
        .header-title .dept-cell {
            display: table-cell;
            padding: 4px 10px;
            font-size: 11pt;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-right {
            display: table-cell;
            width: 160px;
            vertical-align: top;
            padding: 0;
        }
        .header-right .doc-row {
            padding: 2px 10px;
            font-size: 10pt;
            border-bottom: 1px solid #e5e7eb;
        }
        .header-right .doc-row:last-child { border-bottom: none; }
        .header-right .doc-row.highlight { background: #f3f4f6; }

        /* ── Hero — recipe photo + critical info ─────────── */
        .hero {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #000;
        }
        .hero-photo {
            display: table-cell;
            width: 38%;
            vertical-align: middle;
            padding: 6px;
            border-right: 1px solid #000;
            text-align: center;
            background: #fafafa;
        }
        .hero-photo img { max-width: 100%; height: auto; max-height: 230px; }
        .hero-photo .no-photo {
            color: #9ca3af;
            font-size: 11pt;
            padding: 40px 10px;
            font-style: italic;
        }
        .hero-info { display: table-cell; vertical-align: top; padding: 0; }
        .hero-info table { width: 100%; border-collapse: collapse; }
        .hero-info td {
            padding: 6px 10px;
            font-size: 11pt;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
        }
        .hero-info td.label {
            width: 90px;
            font-weight: bold;
            background: #f3f4f6;
            border-right: 1px solid #e5e7eb;
            color: #111;
        }
        .hero-info tr:last-child td { border-bottom: none; }
        .hero-info .pill {
            display: inline-block;
            background: #4338ca;
            color: #fff;
            padding: 2px 8px;
            font-size: 9pt;
            font-weight: bold;
            border-radius: 3px;
            margin-right: 4px;
        }

        /* ── Section header ──────────────────────────────── */
        .section-header {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #000;
        }

        /* ── Ingredient table ────────────────────────────── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border: 1px solid #000;
        }
        table.items thead th {
            background: #1f2937;
            color: #fff;
            padding: 6px 8px;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            font-weight: bold;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10.5pt;
            color: #1f2937;
        }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        table.items tbody td.right { text-align: right; }
        table.items tbody tr:last-child td { border-bottom: none; }

        /* ── Linear steps (no images) — LARGE text ───────── */
        .step-item {
            margin-bottom: 10px;
            padding-left: 28px;
            position: relative;
        }
        .step-item .num {
            position: absolute; left: 0; top: 0;
            width: 22px; height: 22px;
            background: #000; color: #fff;
            text-align: center; line-height: 22px;
            font-size: 11pt; font-weight: bold;
            border-radius: 50%;
        }
        .step-item .step-title-inline {
            font-size: 12pt;
            font-weight: bold;
            color: #000;
            display: block;
            line-height: 1.3;
        }
        .step-item .step-body {
            font-size: 11pt;
            color: #1f2937;
            line-height: 1.55;
            margin-top: 3px;
        }

        /* ── Step cards (3-col grid) — kitchen-readable ─── */
        .step-card {
            border: 1px solid #000;
            background: #fff;
            overflow: hidden;
        }
        .step-card .step-img {
            width: 100%;
            height: 165px;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #000;
        }
        .step-card .step-no-img {
            width: 100%;
            height: 165px;
            background: #f3f4f6;
            border-bottom: 1px solid #000;
        }
        .step-card .step-body-wrap {
            padding: 8px 10px;
        }
        .step-card .step-num {
            display: inline-block;
            background: #000;
            color: #fff;
            min-width: 20px;
            text-align: center;
            padding: 1px 6px;
            font-size: 10pt;
            font-weight: bold;
            border-radius: 2px;
            margin-right: 4px;
        }
        .step-card .step-title {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
        }
        .step-card .step-text {
            font-size: 10.5pt;
            color: #1f2937;
            line-height: 1.5;
            margin-top: 4px;
        }

        /* ── Plating ─────────────────────────────────────── */
        .plating-label {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #111;
            letter-spacing: 1px;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #000;
        }
        .plating-img {
            max-width: 100%;
            height: auto;
            max-height: 230px;
            border: 1px solid #d1d5db;
        }

        /* ── QR ─────────────────────────────────────────── */
        .qr-box { text-align: center; }
        .qr-box img {
            width: 80px; height: 80px;
            border: 1px solid #000;
            padding: 3px;
            background: #fff;
        }
        .qr-box .qr-label {
            font-size: 8pt;
            color: #000;
            font-weight: bold;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Warning / Disclaimer footer ─────────────────── */
        .warning-notice {
            margin-top: 12px;
            padding: 8px 12px;
            border-top: 2px solid #000;
            font-size: 8.5pt;
            color: #374151;
            line-height: 1.5;
        }
        .warning-notice strong { color: #000; }

        /* ── Page footer ─────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 6px 25px;
            border-top: 1px solid #9ca3af;
            font-size: 8pt;
            color: #6b7280;
            background: #fff;
        }
        .footer-left { display: inline-block; }
        .footer-right { display: inline-block; float: right; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>

    <div class="footer">
        <span class="footer-left">
            &copy; {{ now()->format('Y') }} {{ $brandName ?? $company?->name ?? 'Servora' }}
            @if (isset($brandName))
                &middot; Confidential &amp; proprietary
            @endif
        </span>
        <span class="footer-right">
            Generated {{ now()->format('d M Y') }}{{ isset($exportedBy) ? ' &middot; ' . $exportedBy : '' }} &middot; Powered by Servora
        </span>
    </div>
</body>
</html>
