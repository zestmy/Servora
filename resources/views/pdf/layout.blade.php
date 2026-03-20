<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #000; line-height: 1.35; }
        .container { padding: 20px 25px; }
        .header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .company-name { font-size: 13px; font-weight: bold; color: #000; margin-bottom: 1px; }
        .company-detail { font-size: 8px; color: #555; line-height: 1.4; }
        .doc-title { font-size: 12px; font-weight: bold; color: #000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
        .doc-number { font-size: 11px; font-family: monospace; color: #333; }
        .doc-status { display: inline-block; padding: 2px 8px; border: 1px solid #000; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; }
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
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.items thead th { background: #000; color: #fff; padding: 3px 6px; font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items thead th:last-child, table.items thead th.right { text-align: right; }
        table.items thead th.center { text-align: center; }
        table.items tbody td { padding: 3px 6px; border-bottom: 1px solid #eee; font-size: 9px; color: #000; }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.center { text-align: center; }
        table.items tfoot td { padding: 5px 6px; font-weight: bold; font-size: 10px; border-top: 2px solid #000; }
        .notes { margin-top: 8px; padding: 6px; border: 1px solid #ccc; }
        .notes h4 { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px; }
        .notes p { font-size: 9px; color: #000; }
        .footer { margin-top: 15px; padding-top: 6px; border-top: 1px solid #ccc; font-size: 7px; color: #999; text-align: center; }
        .company-logo { max-height: 30px; max-width: 120px; margin-bottom: 3px; }
        .signatures { display: table; width: 100%; margin-top: 30px; }
        .sig-box { display: table-cell; width: 33%; text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-line { border-top: 1px solid #000; padding-top: 4px; margin-top: 8px; font-size: 8px; color: #333; }
        .sig-name { font-size: 9px; font-weight: bold; margin-top: 2px; color: #000; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')

        <div class="footer">
            Generated on {{ now()->format('d M Y, h:i A') }}{{ isset($exportedBy) ? ' by ' . $exportedBy : '' }} | {{ $brandName ?? $company?->name ?? 'Servora' }}
            @if (isset($brandName))
                | Confidential &amp; property of {{ $brandName }}.
            @endif
            | Powered by Servora
        </div>
    </div>
</body>
</html>
