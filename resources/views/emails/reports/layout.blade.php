<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Analytics Report' }}</title>
    <style>
        /* Reset */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

        /* Base */
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #f3f4f6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
        }

        .container {
            max-width: 650px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 24px;
        }

        .card-header h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 700;
        }

        .card-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 24px;
        }

        /* KPI Grid */
        .kpi-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px;
        }

        .kpi-row {
            display: table-row;
        }

        .kpi-card {
            display: table-cell;
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            vertical-align: top;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .kpi-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-trend {
            font-size: 12px;
            margin-top: 4px;
        }

        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-flat { color: #6b7280; }

        /* Section */
        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th {
            background: #f3f4f6;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Charts */
        .chart-container {
            text-align: center;
            margin: 16px 0;
        }

        .chart-container img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        /* AI Insights */
        .insights-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .insights-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .insights-icon {
            width: 24px;
            height: 24px;
            margin-right: 8px;
        }

        .insights-title {
            font-size: 14px;
            font-weight: 600;
            color: #92400e;
        }

        .insights-headline {
            font-size: 16px;
            font-weight: 600;
            color: #78350f;
            margin-bottom: 12px;
        }

        .insights-list {
            margin: 0;
            padding-left: 20px;
        }

        .insights-list li {
            font-size: 14px;
            color: #78350f;
            margin-bottom: 6px;
        }

        .highlight-good { color: #047857; font-weight: 600; }
        .highlight-bad { color: #dc2626; font-weight: 600; }

        /* Comparison Cards */
        .comparison-grid {
            width: 100%;
        }

        .comparison-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 8px;
        }

        .comparison-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .comparison-value {
            font-size: 14px;
            font-weight: 600;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #9ca3af;
        }

        .footer a {
            color: #3b82f6;
            text-decoration: none;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .container { padding: 10px; }
            .card-header { padding: 16px; }
            .card-body { padding: 16px; }
            .kpi-grid { display: block; }
            .kpi-row { display: block; }
            .kpi-card { display: block; width: 100%; margin-bottom: 12px; }
            .kpi-value { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')

        <div class="footer">
            <p>This report was automatically generated by <strong>{{ $companyName }}</strong> via Servora.</p>
            <p>To manage your report subscriptions, <a href="{{ config('app.url') }}/settings/reports">click here</a>.</p>
        </div>
    </div>
</body>
</html>
