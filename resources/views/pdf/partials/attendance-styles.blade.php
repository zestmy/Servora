{{-- Shared by the attendance grid and the service charge distribution.

     They were one document until the two were split apart, and they still have
     to look like a matched pair — same header rule, same type scale, same
     signature block. One stylesheet is what keeps that true; two would drift
     the first time either was touched. --}}
    <style>
        /* Scoped reset — `html` (or `*`) must NOT be reset here: dompdf
           implements @page margins via the root element, so `html { margin: 0 }`
           silently zeroes the page margins. */
        body, div, span, h1, h2, h3, p, img, table, thead, tbody, tr, th, td { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 16mm 14mm; }
        /* DejaVu Sans ships with dompdf and renders the ✓ glyph. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.3; }

        .header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 1.5px solid #2d3748; padding-bottom: 7px; }
        .header-left { display: table-cell; vertical-align: middle; width: 55%; }
        .header-right { display: table-cell; vertical-align: middle; width: 45%; text-align: right; }
        .logo { max-height: 30px; max-width: 95px; margin-right: 6px; vertical-align: middle; }
        .brand { font-size: 11px; font-weight: bold; vertical-align: middle; }
        .title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #374151; }
        .meta { font-size: 7px; color: #6b7280; margin-top: 2px; }

        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        /* Only rendered when there are two tables to tell apart. */
        .table-title { font-size: 8pt; font-weight: bold; color: #334155;
                       margin: 10px 0 3px; text-transform: uppercase; letter-spacing: 0.6px; }
        table.grid th, table.grid td { border: 0.6px solid #cbd5e1; overflow: hidden; }

        thead th {
            background: #2d3748; color: #fff; padding: 2.5px 2px;
            font-size: 6px; text-transform: uppercase; letter-spacing: 0.3px;
            font-weight: bold; text-align: center;
        }
        thead th.info { text-align: left; padding-left: 3px; }
        thead th .dow { display: block; font-size: 5px; font-weight: normal; opacity: 0.75; }
        thead th.sun { background: #7f1d1d; }
        thead th.sat { background: #78350f; }

        tbody td { padding: 2.5px 2px; font-size: 6.5px; vertical-align: middle; }
        td.info { text-align: left; padding-left: 3px; white-space: nowrap; }
        td.name { font-weight: bold; }
        td.num { text-align: center; color: #6b7280; }
        /* Salary is too wide for the grid on one line, so the pay-type suffix
           drops underneath the figure rather than being clipped. */
        td.pay { text-align: right; padding-right: 3px; white-space: nowrap; color: #374151; }
        td.pay .suffix { display: block; font-size: 5px; color: #6b7280; }
        td.day { text-align: center; font-weight: bold; font-size: 6px; padding: 1.5px 0; }
        td.sun-empty { background: #fef2f2; }
        td.total { text-align: center; font-weight: bold; background: #f8fafc; }

        .outlet-row td {
            background: #e2e8f0; font-weight: bold; font-size: 7px;
            padding: 2.5px 4px; color: #1e293b; text-align: left;
        }

        .sc { margin-top: 14px; }
        .sc-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #0f766e; margin-bottom: 2px; }
        .sc-meta { font-size: 6.5px; color: #6b7280; margin-bottom: 4px; }
        table.sc-table { width: 100%; border-collapse: collapse; }
        table.sc-table th, table.sc-table td { border: 0.6px solid #cbd5e1; padding: 2.5px 3px; font-size: 6.5px; }
        table.sc-table thead th { background: #0f766e; color: #fff; font-size: 6px; text-transform: uppercase; letter-spacing: 0.3px; text-align: center; }
        table.sc-table td.l { text-align: left; }
        table.sc-table td.r { text-align: right; }
        table.sc-table td.c { text-align: center; }
        table.sc-table tr.sc-total td { background: #f0fdfa; font-weight: bold; }
        .sc-note { font-size: 6px; color: #94a3b8; margin-top: 3px; }

        .legend { margin-top: 14px; }
        .legend-title { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 3px; }
        table.legend-table { width: 100%; border-collapse: collapse; }
        table.legend-table td { padding: 1.5px 8px 1.5px 0; font-size: 6.5px; color: #374151; width: 25%; }
        .swatch {
            display: inline-block; min-width: 22px; padding: 1px 3px; margin-right: 3px;
            border: 0.5px solid rgba(0,0,0,0.12); border-radius: 2px;
            font-weight: bold; font-size: 6px; text-align: center;
        }

        .signatures { display: table; width: 100%; margin-top: 26px; }
        .sig { display: table-cell; width: 33%; padding-right: 35px; }
        .sig-line { border-top: 0.8px solid #475569; margin-top: 32px; padding-top: 3px; font-size: 6.5px; color: #475569; }
        .sig-label { font-size: 7px; font-weight: bold; color: #1e293b; }

        .footer { margin-top: 14px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 6.5px; color: #94a3b8; text-align: right; }
    </style>
