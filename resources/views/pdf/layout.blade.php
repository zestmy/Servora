<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @@page { margin: 22mm 20mm 22mm 20mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            color: #1f2937;
            line-height: 1.5;
        }

        /* ═══ Document Header ═══════════════════════════════════ */
        table.doc-header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.doc-header td { vertical-align: middle; padding: 0; }
        .dh-logo {
            width: 95px;
            padding-right: 18px;
            text-align: left;
            vertical-align: middle;
        }
        .dh-logo img { max-height: 75px; max-width: 90px; display: block; }
        .dh-body { padding: 0; vertical-align: middle; }
        .dh-brand {
            font-size: 14pt;
            font-weight: bold;
            color: #1f2937;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }
        .dh-company {
            font-size: 9.5pt;
            color: #6b7280;
            letter-spacing: 0.2px;
            margin-top: 1px;
        }
        .dh-divider {
            width: 48px;
            height: 3px;
            background: #111827;
            margin: 8px 0 8px 0;
        }
        .dh-recipe {
            font-size: 22pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.8px;
            line-height: 1.1;
            margin-bottom: 3px;
        }
        .dh-subtitle {
            font-size: 9.5pt;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 2.2px;
            font-weight: bold;
        }
        .dh-description {
            font-size: 10.5pt;
            color: #475569;
            font-style: italic;
            margin-top: 6px;
            line-height: 1.5;
        }

        /* Subtle accent rule under header */
        .header-rule {
            height: 1px;
            background: #cbd5e1;
            margin-bottom: 18px;
        }

        /* ═══ Hero: photo + key info ════════════════════════════ */
        table.hero { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.hero > tbody > tr > td { vertical-align: top; padding: 0; }
        .hero-photo-cell {
            width: 42%;
            padding-right: 14px;
            vertical-align: middle !important;
        }
        .hero-photo-frame {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 4px;
            text-align: center;
        }
        .hero-photo-frame img { max-width: 100%; height: auto; max-height: 260px; display: block; margin: 0 auto; }
        .hero-photo-frame .no-photo { color: #94a3b8; font-size: 10.5pt; padding: 70px 10px; font-style: italic; }

        table.hero-info { width: 100%; border-collapse: collapse; }
        table.hero-info td {
            padding: 10px 14px;
            font-size: 11pt;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        table.hero-info td.label {
            width: 95px;
            font-weight: bold;
            color: #475569;
            font-size: 9.5pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        table.hero-info tr:first-child td { border-top: 2px solid #0f172a; }
        table.hero-info tr:last-child td { border-bottom: 2px solid #0f172a; }
        table.hero-info .big-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
        }

        /* ═══ Section header ═══════════════════════════════════ */
        .section-header {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            color: #0f172a;
            margin: 0 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #0f172a;
        }

        /* ═══ Linear Steps ══════════════════════════════════════ */
        table.step-list { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.step-list td { padding: 8px 0; vertical-align: top; }
        table.step-list td.num-cell {
            width: 38px;
            padding-right: 14px;
            text-align: center;
        }
        table.step-list td.num-cell .num {
            display: inline-block;
            width: 26px; height: 26px;
            background: #0f172a; color: #fff;
            text-align: center; line-height: 26px;
            font-size: 11.5pt; font-weight: bold;
            border-radius: 50%;
        }
        table.step-list .step-title-inline {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            display: block;
            line-height: 1.3;
        }
        table.step-list .step-body {
            font-size: 11pt;
            color: #1f2937;
            line-height: 1.6;
            margin-top: 4px;
        }

        /* ═══ Step cards grid ═══════════════════════════════════ */
        .step-card {
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .step-card .step-img {
            width: 100%;
            height: 155px;
            display: block;
            border-bottom: 1px solid #e2e8f0;
        }
        .step-card .step-no-img {
            width: 100%; height: 155px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .step-card-body { padding: 10px 12px; }
        .step-card .step-num {
            display: inline-block;
            background: #0f172a; color: #fff;
            min-width: 22px;
            text-align: center;
            padding: 2px 7px;
            font-size: 10pt;
            font-weight: bold;
            margin-right: 5px;
        }
        .step-card .step-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
        }
        .step-card .step-text {
            font-size: 10.5pt;
            color: #334155;
            line-height: 1.55;
            margin-top: 5px;
        }

        /* ═══ Ingredient list (hero) ════════════════════════════ */
        table.ing-table { width: 100%; border-collapse: collapse; }
        table.ing-table td { padding: 2px 0; font-size: 10.5pt; color: #1f2937; vertical-align: top; border: none; }
        table.ing-table td.ing-bullet { width: 12px; color: #94a3b8; font-weight: bold; padding-right: 4px; }
        table.ing-table td.ing-name { font-weight: bold; color: #0f172a; white-space: nowrap; padding-right: 10px; }
        table.ing-table td.ing-qty { color: #475569; text-align: right; white-space: nowrap; }

        /* ═══ Plating ═══════════════════════════════════════════ */
        .plating-label {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 1.8px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
        }
        .plating-img {
            max-width: 100%;
            height: auto;
            max-height: 240px;
            border: 1px solid #e2e8f0;
        }

        /* ═══ QR ═══════════════════════════════════════════════ */
        .qr-img {
            width: 80px; height: 80px;
            border: 1px solid #cbd5e1;
            padding: 3px;
            background: #fff;
        }
        .qr-label {
            font-size: 7.5pt;
            color: #475569;
            font-weight: bold;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }

        /* ═══ Warning ══════════════════════════════════════════ */
        .warning-notice {
            margin-top: 18px;
            padding: 10px 14px;
            border-top: 1px solid #cbd5e1;
            font-size: 8.5pt;
            color: #64748b;
            line-height: 1.6;
            font-style: italic;
        }
        .warning-notice strong {
            color: #0f172a;
            font-style: normal;
            letter-spacing: 0.5px;
        }

        /* ═══ Footer ═══════════════════════════════════════════ */
        .pdf-footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 8pt;
            color: #94a3b8;
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
