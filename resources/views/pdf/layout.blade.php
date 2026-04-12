<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 20mm 15mm 18mm 15mm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            color: #1f2937;
            line-height: 1.45;
        }

        /* ── Compact document header (single row, real table) ── */
        table.doc-header { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 10px; }
        table.doc-header td { vertical-align: middle; padding: 0; }
        .dh-logo-cell { width: 80px; text-align: center; border-right: 1px solid #000; padding: 6px; }
        .dh-logo-cell img { max-height: 55px; max-width: 70px; }
        .dh-title-cell { border-right: 1px solid #000; padding: 0; }
        .dh-title-main { font-size: 14pt; font-weight: bold; color: #000; padding: 5px 10px 2px; letter-spacing: 0.3px; }
        .dh-title-sub { font-size: 10.5pt; color: #333; font-style: italic; padding: 0 10px 4px; }
        .dh-dept { font-size: 10.5pt; font-weight: bold; color: #000; padding: 4px 10px; background: #f3f4f6; border-top: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.5px; }
        .dh-meta-cell { width: 150px; padding: 0; }
        table.dh-meta { width: 100%; border-collapse: collapse; }
        table.dh-meta td { padding: 3px 8px; font-size: 9.5pt; border-bottom: 1px solid #e5e7eb; }
        table.dh-meta tr:last-child td { border-bottom: none; }
        table.dh-meta tr.hl td { background: #f3f4f6; }

        /* ── Hero ──────────────────────────────────────── */
        table.hero { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 10px; }
        table.hero > tbody > tr > td { vertical-align: top; padding: 0; }
        .hero-photo-cell { width: 38%; text-align: center; border-right: 1px solid #000; padding: 6px; background: #fafafa; vertical-align: middle !important; }
        .hero-photo-cell img { max-width: 100%; height: auto; max-height: 220px; }
        .hero-photo-cell .no-photo { color: #9ca3af; font-size: 10.5pt; padding: 30px 10px; font-style: italic; }
        table.hero-info { width: 100%; border-collapse: collapse; }
        table.hero-info td { padding: 6px 10px; font-size: 10.5pt; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        table.hero-info td.label { width: 90px; font-weight: bold; background: #f3f4f6; border-right: 1px solid #e5e7eb; color: #111; }
        table.hero-info tr:last-child td { border-bottom: none; }

        /* ── Section header ─────────────────────────────── */
        .section-header {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000;
            margin: 0 0 6px;
            padding-bottom: 3px;
            border-bottom: 2px solid #000;
        }

        /* ── Linear steps (readable) ────────────────────── */
        table.step-list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.step-list td { padding: 6px 0; vertical-align: top; }
        table.step-list td.num-cell { width: 32px; padding-right: 10px; text-align: center; }
        table.step-list td.num-cell .num {
            display: inline-block;
            width: 22px; height: 22px;
            background: #000; color: #fff;
            text-align: center; line-height: 22px;
            font-size: 11pt; font-weight: bold;
            border-radius: 50%;
        }
        table.step-list .step-title-inline { font-size: 12pt; font-weight: bold; color: #000; display: block; line-height: 1.3; }
        table.step-list .step-body { font-size: 11pt; color: #1f2937; line-height: 1.55; margin-top: 3px; }

        /* ── Step cards grid ────────────────────────────── */
        .step-card { border: 1px solid #000; background: #fff; }
        .step-card .step-img { width: 100%; height: 155px; display: block; border-bottom: 1px solid #000; }
        .step-card .step-no-img { width: 100%; height: 155px; background: #f3f4f6; border-bottom: 1px solid #000; }
        .step-card-body { padding: 8px 10px; }
        .step-card .step-num {
            display: inline-block;
            background: #000; color: #fff;
            min-width: 20px; text-align: center;
            padding: 1px 6px;
            font-size: 10pt; font-weight: bold;
            margin-right: 4px;
        }
        .step-card .step-title { font-size: 11pt; font-weight: bold; color: #000; }
        .step-card .step-text { font-size: 10.5pt; color: #1f2937; line-height: 1.5; margin-top: 4px; }

        /* ── Ingredient table ───────────────────────────── */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #000; }
        table.items thead th {
            background: #1f2937; color: #fff;
            padding: 6px 8px;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            font-weight: bold;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10.5pt; color: #1f2937; }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        table.items tbody td.right { text-align: right; }
        table.items tbody tr:last-child td { border-bottom: none; }

        /* ── Plating ───────────────────────────────────── */
        .plating-label {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #000;
            letter-spacing: 1px;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #000;
        }
        .plating-img { max-width: 100%; height: auto; max-height: 220px; border: 1px solid #d1d5db; }

        /* ── QR ────────────────────────────────────────── */
        .qr-img { width: 75px; height: 75px; border: 1px solid #000; padding: 2px; }
        .qr-label {
            font-size: 8pt; color: #000; font-weight: bold;
            margin-top: 3px; text-transform: uppercase; letter-spacing: 0.5px;
            text-align: center;
        }

        /* ── Warning ────────────────────────────────────── */
        .warning-notice {
            margin-top: 12px;
            padding: 8px 12px;
            border-top: 2px solid #000;
            font-size: 8.5pt;
            color: #374151;
            line-height: 1.5;
        }
        .warning-notice strong { color: #000; }

        /* ── Footer (rendered inline — dompdf-compatible) ── */
        .pdf-footer {
            margin-top: 20px;
            padding-top: 6px;
            border-top: 1px solid #9ca3af;
            font-size: 8pt;
            color: #6b7280;
        }
        .pdf-footer .left { float: left; }
        .pdf-footer .right { float: right; }
    </style>
</head>
<body>
    @yield('content')

    <div class="pdf-footer">
        <span class="left">
            &copy; {{ now()->format('Y') }} {{ $brandName ?? $company?->name ?? 'Servora' }}
            @if (isset($brandName))
                &middot; Confidential &amp; proprietary
            @endif
        </span>
        <span class="right">
            Generated {{ now()->format('d M Y') }}{{ isset($exportedBy) ? ' · ' . $exportedBy : '' }} &middot; Powered by Servora
        </span>
    </div>
</body>
</html>
