<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #000; line-height: 1.5; }
        .container { padding: 30px; }
        .header { display: table; width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .company-name { font-size: 16px; font-weight: bold; color: #000; margin-bottom: 2px; }
        .company-detail { font-size: 9px; color: #444; line-height: 1.6; }
        .doc-title { font-size: 18px; font-weight: bold; color: #000; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .doc-number { font-size: 13px; font-family: monospace; color: #333; }
        .doc-status { display: inline-block; padding: 2px 8px; border: 1px solid #000; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; }
        .info-grid { display: table; width: 100%; margin-bottom: 18px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; padding: 10px; border: 1px solid #ccc; }
        .info-box:first-child { border-right: none; }
        .info-box h4 { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #666; letter-spacing: 0.5px; margin-bottom: 5px; }
        .info-box p { font-size: 10px; color: #000; line-height: 1.6; }
        .info-box .name { font-weight: bold; font-size: 11px; }
        .meta-table { width: 100%; margin-bottom: 15px; }
        .meta-table td { padding: 3px 0; font-size: 10px; }
        .meta-table .label { color: #666; width: 120px; }
        .meta-table .value { color: #000; font-weight: 500; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items thead th { background: #000; color: #fff; padding: 6px 8px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items thead th:last-child, table.items thead th.right { text-align: right; }
        table.items thead th.center { text-align: center; }
        table.items tbody td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 10px; color: #000; }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.center { text-align: center; }
        table.items tbody tr:nth-child(even) { background: #f5f5f5; }
        table.items tfoot td { padding: 8px; font-weight: bold; font-size: 11px; border-top: 2px solid #000; }
        .notes { margin-top: 12px; padding: 10px; border: 1px solid #ccc; }
        .notes h4 { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 3px; }
        .notes p { font-size: 10px; color: #000; }
        .footer { margin-top: 35px; padding-top: 12px; border-top: 1px solid #ccc; font-size: 8px; color: #999; text-align: center; }
        .company-logo { max-height: 45px; max-width: 160px; margin-bottom: 6px; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .sig-box { display: table-cell; width: 33%; text-align: center; vertical-align: bottom; padding: 0 15px; }
        .sig-line { border-top: 1px solid #000; padding-top: 5px; margin-top: 10px; font-size: 9px; color: #333; }
        .sig-name { font-size: 10px; font-weight: bold; margin-top: 3px; color: #000; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')

        <div class="footer">
            Generated on {{ now()->format('d M Y, h:i A') }}{{ isset($exportedBy) ? ' by ' . $exportedBy : '' }} | {{ $brandName ?? $company?->name ?? 'Servora' }}
            @if (isset($brandName))
                <br>This manual is confidential &amp; property of {{ $brandName }}.
            @endif
            <br>Powered by Servora - https://servora.com.my/
        </div>
    </div>
</body>
</html>
