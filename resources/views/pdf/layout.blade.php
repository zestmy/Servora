<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        /* Universal reset (excluding body — DomPDF uses body margin for page padding) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Page margins */
        @@page { margin: 12mm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.5;
            margin: 12mm;
        }

        /* ═══ Document Header — compact & elegant ═══════════════ */
        table.doc-header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.doc-header td { vertical-align: middle; padding: 0; }
        .dh-logo {
            width: 70px;
            padding-right: 10px;
            vertical-align: middle;
        }
        .dh-logo img { max-height: 52px; max-width: 65px; display: block; }
        .dh-body {
            vertical-align: middle;
            padding: 0 10px;
            border-left: 1px solid #e2e8f0;
        }
        .dh-recipe-cell {
            vertical-align: middle;
            padding: 0 10px;
            border-left: 1px solid #e2e8f0;
        }
        .dh-qr-cell {
            width: 80px;
            vertical-align: middle;
            text-align: right;
            padding-left: 10px;
            border-left: 1px solid #e2e8f0;
        }
        .dh-qr-box { display: inline-block; text-align: center; }
        .dh-qr-box img { width: 62px; height: 62px; border: 1px solid #cbd5e1; padding: 2px; }
        .dh-qr-box .qr-label { font-size: 6.5pt; color: #64748b; font-weight: bold; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .dh-brand {
            font-size: 11pt;
            font-weight: bold;
            color: #1f2937;
            letter-spacing: -0.1px;
            line-height: 1.25;
        }
        .dh-company {
            font-size: 8.5pt;
            color: #6b7280;
            margin-top: 1px;
        }
        .dh-sop-label {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: bold;
            margin-top: 5px;
        }
        .dh-recipe {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.3px;
            line-height: 1.15;
            margin-bottom: 3px;
        }
        .dh-subtitle {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: bold;
        }
        .dh-description {
            font-size: 9.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 4px;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .header-rule {
            height: 1px;
            background: #e2e8f0;
            margin-bottom: 12px;
        }

        /* ═══ Hero ══════════════════════════════════════════════ */
        table.hero { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.hero > tbody > tr > td { vertical-align: top; padding: 0; }
        .hero-photo-cell {
            width: 40%;
            padding-right: 12px;
            vertical-align: middle !important;
        }
        .hero-photo-frame {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 3px;
            text-align: center;
        }
        .hero-photo-frame img { max-width: 100%; height: auto; max-height: 210px; display: block; margin: 0 auto; }
        .hero-photo-frame .no-photo { color: #94a3b8; font-size: 9pt; padding: 50px 10px; font-style: italic; }

        table.hero-info { width: 100%; border-collapse: collapse; }
        table.hero-info td {
            padding: 7px 11px;
            font-size: 10pt;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        table.hero-info td.label {
            width: 78px;
            font-weight: bold;
            color: #475569;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        table.hero-info tr:first-child td { border-top: 1.5px solid #0f172a; }
        table.hero-info tr:last-child td { border-bottom: 1.5px solid #0f172a; }
        table.hero-info .big-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
        }

        /* ═══ Section header ════════════════════════════════════ */
        .section-header {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #0f172a;
            margin: 14px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #0f172a;
        }

        /* ═══ Linear Steps ══════════════════════════════════════ */
        table.step-list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.step-list td { padding: 6px 0; vertical-align: top; }
        table.step-list td.num-cell { width: 28px; padding-right: 10px; text-align: center; }
        table.step-list td.num-cell .num {
            display: inline-block;
            width: 20px; height: 20px;
            background: #0f172a; color: #fff;
            text-align: center; line-height: 20px;
            font-size: 9.5pt; font-weight: bold;
            border-radius: 50%;
        }
        table.step-list .step-title-inline {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            display: block;
            line-height: 1.3;
        }
        table.step-list .step-body {
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.55;
            margin-top: 2px;
        }

        /* ═══ Step cards grid ═══════════════════════════════════ */
        .step-card {
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .step-card .step-img {
            width: 100%;
            height: 135px;
            display: block;
            border-bottom: 1px solid #e2e8f0;
        }
        .step-card .step-no-img {
            width: 100%; height: 135px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .step-card-body { padding: 7px 10px; }
        .step-card .step-num {
            display: inline-block;
            background: #0f172a; color: #fff;
            min-width: 18px;
            text-align: center;
            padding: 1px 6px;
            font-size: 9pt;
            font-weight: bold;
            margin-right: 4px;
        }
        .step-card .step-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
        }
        .step-card .step-text {
            font-size: 9.5pt;
            color: #334155;
            line-height: 1.45;
            margin-top: 3px;
        }

        /* ═══ Ingredient list ═══════════════════════════════════ */
        table.ing-table { width: 100%; border-collapse: collapse; }
        table.ing-table td { padding: 1.5px 0; font-size: 8.5pt; color: #1f2937; vertical-align: top; border: none; }
        table.ing-table td.ing-bullet { width: 10px; color: #94a3b8; font-weight: bold; padding-right: 3px; }
        table.ing-table td.ing-name { font-weight: bold; color: #0f172a; padding-right: 12px; }
        table.ing-table td.ing-qty { color: #475569; text-align: right; white-space: nowrap; }
        table.hero-info td.ing-list-cell { padding: 7px 11px; background: #fff; }

        /* ═══ Plating ═══════════════════════════════════════════ */
        .plating-label {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 1.5px;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #cbd5e1;
        }
        .plating-img {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            border: 1px solid #e2e8f0;
        }

        /* ═══ QR ═══════════════════════════════════════════════ */
        .qr-img {
            width: 65px; height: 65px;
            border: 1px solid #cbd5e1;
            padding: 2px;
            background: #fff;
        }
        .qr-label {
            font-size: 7pt;
            color: #64748b;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: center;
        }

        /* ═══ Warning ══════════════════════════════════════════ */
        .warning-notice {
            margin-top: 14px;
            padding: 8px 12px;
            border-top: 1px solid #cbd5e1;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.55;
            font-style: italic;
        }
        .warning-notice strong {
            color: #0f172a;
            font-style: normal;
            letter-spacing: 0.4px;
        }

        /* ═══ Footer ═══════════════════════════════════════════ */
        .pdf-footer {
            margin-top: 18px;
            padding-top: 6px;
            border-top: 1px solid #cbd5e1;
            font-size: 7pt;
            color: #94a3b8;
        }
        .pdf-footer .left { float: left; }
        .pdf-footer .right { float: right; }

        /* ═══ Legacy header (used by recipe-cost-summary, etc) ═══ */
        .header {
            display: table; width: 100%;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0f172a;
        }
        .header-left { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .company-logo { max-height: 36px; max-width: 130px; margin-bottom: 4px; vertical-align: middle; display: inline-block; }
        .company-name { font-size: 13pt; font-weight: bold; color: #0f172a; letter-spacing: -0.2px; }
        .doc-title { font-size: 11pt; font-weight: bold; color: #1f2937; text-transform: uppercase; letter-spacing: 1.5px; }
        .doc-number { font-size: 10pt; color: #4b5563; margin-top: 2px; }

        /* ═══ Items table (used by recipe-cost-summary) ═══ */
        table.items {
            width: 100%; border-collapse: collapse;
            margin-bottom: 8px; border: 1px solid #cbd5e1;
        }
        table.items thead th {
            background: #1f2937; color: #fff;
            padding: 6px 7px;
            font-size: 8.5pt;
            text-transform: uppercase; letter-spacing: 0.7px;
            text-align: left; font-weight: bold;
        }
        table.items thead th.right { text-align: right; }
        table.items thead th.center { text-align: center; }
        table.items tbody td {
            padding: 4px 7px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt; color: #1f2937;
        }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.center { text-align: center; }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
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
