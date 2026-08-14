<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate — {{ $certificate->recipient_name }}</title>
    <style>
        /* Scoped reset — `html` (or `*`) must NOT be reset here: dompdf
           implements @page margins via the root element, so `html { margin: 0 }`
           silently zeroes the page margins. */
        body, div, span, p, h1, h2, h3, small, strong, img, table, tr, td { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 0; }

        /*
         * Every colour here is a literal hex, and that is correct rather than a
         * lapse: dompdf never sees the stylesheet, so a Tailwind token would
         * render as nothing. The teal below is brand-600 — if the brand scale in
         * tailwind.config.js moves, this has to be changed by hand. That is
         * exactly the trade the design system's "never hardcode brand hex" rule
         * calls out as unavoidable in PDF templates.
         */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1a1a1a;
        }

        .sheet {
            width: 297mm; height: 210mm;
            padding: 14mm;
        }
        .frame {
            width: 100%; height: 100%;
            border: 2px solid #0d9488;
            padding: 12mm 14mm;
            position: relative;
        }

        .brandline { text-align: center; }
        .logo { max-height: 46px; max-width: 190px; }
        .company { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; color: #334155; }

        .kicker {
            margin-top: 14mm; text-align: center;
            font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: #64748b;
        }
        .name {
            margin-top: 5mm; text-align: center;
            font-size: 34px; font-weight: bold; color: #0f172a;
        }
        .rule { width: 90mm; height: 1px; background: #cbd5e1; margin: 6mm auto; }
        .for {
            text-align: center; font-size: 11px; color: #475569;
        }
        .course {
            margin-top: 3mm; text-align: center;
            font-size: 19px; font-weight: bold; color: #0d9488;
        }
        .score { margin-top: 4mm; text-align: center; font-size: 11px; color: #475569; }

        .footer {
            position: absolute; left: 14mm; right: 14mm; bottom: 10mm;
        }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { font-size: 8.5px; color: #64748b; vertical-align: bottom; }
        .footer td.r { text-align: right; }
        .serial { font-family: 'Courier', monospace; font-size: 9px; color: #334155; letter-spacing: 0.5px; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="frame">
        <div class="brandline">
            @php
                // dataUri, not url(): dompdf would need remote fetching switched
                // on and the ability to reach the app's own public URL to render
                // a link. See App\Services\Branding\CompanyLogo.
                $logo = app(\App\Services\Branding\CompanyLogo::class)->dataUri($company?->id);
            @endphp
            @if ($logo)
                <img src="{{ $logo }}" class="logo" alt="">
            @else
                <div class="company">{{ $company->brand_name ?? $company->name ?? '' }}</div>
            @endif
        </div>

        <div class="kicker">Certificate of completion</div>

        {{-- The FROZEN name and title, not the live relations. A certificate is
             a statement about a moment. See App\Models\TrainingCertificate. --}}
        <div class="name">{{ $certificate->recipient_name }}</div>

        <div class="rule"></div>

        <div class="for">has successfully completed</div>
        <div class="course">{{ $certificate->title }}</div>

        <div class="score">
            Scored {{ rtrim(rtrim(number_format((float) $certificate->percent, 1), '0'), '.') }}%
            on {{ $certificate->issued_at?->format('j F Y') }}
            @if ($certificate->expires_on)
                &nbsp;·&nbsp; Valid until {{ $certificate->expires_on->format('j F Y') }}
            @endif
        </div>

        <div class="footer">
            <table>
                <tr>
                    <td>
                        Issued by {{ $company->name ?? 'the company' }}
                        <div class="serial">{{ $certificate->serial }}</div>
                    </td>
                    <td class="r">
                        Verify this certificate by its serial number
                        <div class="serial">{{ config('app.name', 'Servora') }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
