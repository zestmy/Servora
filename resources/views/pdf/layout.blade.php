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
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }
        .container { padding: 24px 28px 60px; }

        /* ── Header ──────────────────────────────────────── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4f46e5;
        }
        .header-left { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            letter-spacing: -0.3px;
            margin-bottom: 2px;
        }
        .company-detail { font-size: 8px; color: #6b7280; line-height: 1.5; }
        .company-logo { max-height: 36px; max-width: 140px; margin-bottom: 4px; }

        .doc-badge {
            display: inline-block;
            background: #4f46e5;
            color: #fff;
            padding: 3px 10px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-radius: 3px;
            margin-bottom: 5px;
        }
        .doc-title {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 10px;
            font-family: 'Courier New', monospace;
            color: #4b5563;
            margin-top: 2px;
        }
        .doc-confidential {
            display: inline-block;
            border: 1.5px solid #dc2626;
            color: #dc2626;
            padding: 2px 8px;
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-radius: 2px;
            margin-bottom: 4px;
        }

        /* ── Recipe hero card ────────────────────────────── */
        .recipe-hero {
            background: #f9fafb;
            border-left: 4px solid #4f46e5;
            padding: 10px 14px;
            margin-bottom: 12px;
            display: table;
            width: 100%;
        }
        .recipe-hero-left { display: table-cell; vertical-align: middle; }
        .recipe-hero-right { display: table-cell; vertical-align: middle; width: 90px; text-align: right; }
        .recipe-name {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            letter-spacing: -0.4px;
            margin-bottom: 3px;
        }
        .recipe-meta {
            font-size: 8.5px;
            color: #6b7280;
        }
        .recipe-meta .pill {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 1px 7px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 7.5px;
            margin-right: 4px;
        }
        .recipe-description {
            font-size: 8.5px;
            color: #4b5563;
            margin-top: 4px;
            font-style: italic;
        }

        /* ── Section headers ─────────────────────────────── */
        .section-header {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #4f46e5;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #4f46e5;
        }
        .section-header-sm {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4f46e5;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #e0e7ff;
        }

        /* ── Ingredient table ───────────────────────────── */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.items thead th {
            background: #1f2937;
            color: #fff;
            padding: 5px 7px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            font-weight: bold;
        }
        table.items thead th:last-child, table.items thead th.right { text-align: right; }
        table.items thead th.center { text-align: center; }
        table.items tbody td {
            padding: 4px 7px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 9px;
            color: #1f2937;
        }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.center { text-align: center; }

        /* ── Step cards (3-col grid) ─────────────────────── */
        .step-card {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 0;
            background: #fff;
            overflow: hidden;
        }
        .step-card .step-num {
            display: inline-block;
            background: #4f46e5;
            color: #fff;
            width: 16px;
            height: 16px;
            text-align: center;
            line-height: 16px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 8px;
            margin-right: 4px;
        }
        .step-card .step-title {
            font-size: 9px;
            font-weight: bold;
            color: #111827;
        }
        .step-card .step-text {
            font-size: 8px;
            color: #374151;
            line-height: 1.4;
            margin-top: 2px;
        }

        /* ── Linear steps (when no images) ───────────────── */
        .step-item {
            margin-bottom: 6px;
            padding-left: 22px;
            position: relative;
        }
        .step-item .num {
            position: absolute;
            left: 0; top: 0;
            width: 16px; height: 16px;
            background: #4f46e5;
            color: #fff;
            text-align: center;
            line-height: 16px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 8px;
        }
        .step-item .step-title-inline {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            display: block;
            line-height: 1.3;
        }
        .step-item .step-body {
            font-size: 9px;
            color: #374151;
            line-height: 1.5;
            margin-top: 2px;
        }

        /* ── Plating section ─────────────────────────────── */
        .plating-label {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.8px;
            margin-bottom: 3px;
        }
        .plating-img {
            max-width: 100%;
            height: auto;
            max-height: 180px;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
        }

        /* ── QR code ────────────────────────────────────── */
        .qr-box {
            text-align: center;
        }
        .qr-box img {
            width: 70px; height: 70px;
            border: 1px solid #e5e7eb;
            padding: 2px;
            background: #fff;
        }
        .qr-box .qr-label {
            font-size: 6.5px;
            color: #4f46e5;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Footer ─────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 8px 28px;
            border-top: 1px solid #e5e7eb;
            font-size: 7px;
            color: #9ca3af;
            background: #fff;
        }
        .footer-left { display: inline-block; }
        .footer-right { display: inline-block; float: right; }

        /* Legacy styles kept for compatibility */
        .info-grid { display: table; width: 100%; margin-bottom: 10px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; padding: 6px; border: 1px solid #ccc; }
        .info-box:first-child { border-right: none; }
        .info-box h4 { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #666; letter-spacing: 0.5px; margin-bottom: 3px; }
        .info-box p { font-size: 9px; color: #000; line-height: 1.4; }
        .info-box .name { font-weight: bold; font-size: 10px; }
        .meta-table { width: 100%; margin-bottom: 8px; }
        .meta-table td { padding: 2px 0; font-size: 9px; }
        .meta-table .label { color: #666; width: 90px; }
        .meta-table .value { color: #000; font-weight: 500; }
        table.items tfoot td { padding: 5px 6px; font-weight: bold; font-size: 10px; border-top: 2px solid #000; }
        .notes { margin-top: 8px; padding: 6px; border: 1px solid #ccc; }
        .notes h4 { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px; }
        .notes p { font-size: 9px; color: #000; }
        .signatures { display: table; width: 100%; margin-top: 30px; }
        .sig-box { display: table-cell; width: 33%; text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-line { border-top: 1px solid #000; padding-top: 4px; margin-top: 8px; font-size: 8px; color: #333; }
        .sig-name { font-size: 9px; font-weight: bold; margin-top: 2px; color: #000; }
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
            Generated {{ now()->format('d M Y, h:i A') }}{{ isset($exportedBy) ? ' &middot; ' . $exportedBy : '' }} &middot; Powered by Servora
        </span>
    </div>
</body>
</html>
