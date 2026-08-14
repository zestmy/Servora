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
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1a1a1a; }

        /*
         * NO HEIGHT ON ANYTHING IN NORMAL FLOW, and this is the fix for a
         * certificate that printed across two pages with the second one blank.
         * The old sheet was `height: 210mm` — exactly the page — so the first
         * sub-millimetre of rounding pushed it over and dompdf obligingly
         * started page two. Nothing here declares a height; the content is
         * short, the decoration is position:fixed and therefore outside the
         * flow entirely, and one page is the only possible outcome.
         *
         * position:fixed in dompdf means "relative to the page", which is
         * precisely what a border and a footer want.
         */
        .edge {
            position: fixed; top: 7mm; left: 7mm; right: 7mm; bottom: 7mm;
            border: 3px solid #0d9488;
        }
        .edge-inner {
            position: fixed; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm;
            border: 1px solid #99f6e4;
        }

        /* Corner marks. Four L-shapes, drawn as a pair of borders on an
           otherwise empty box — no transforms, which dompdf renders unreliably. */
        .corner { position: fixed; width: 14mm; height: 14mm; }
        .corner-tl { top: 13mm; left: 13mm; border-top: 2px solid #0d9488; border-left: 2px solid #0d9488; }
        .corner-tr { top: 13mm; right: 13mm; border-top: 2px solid #0d9488; border-right: 2px solid #0d9488; }
        .corner-bl { bottom: 13mm; left: 13mm; border-bottom: 2px solid #0d9488; border-left: 2px solid #0d9488; }
        .corner-br { bottom: 13mm; right: 13mm; border-bottom: 2px solid #0d9488; border-right: 2px solid #0d9488; }

        .body { padding: 22mm 32mm 0 32mm; text-align: center; }

        .logo { max-height: 42px; max-width: 170px; }
        .company {
            font-size: 12px; font-weight: bold; letter-spacing: 2px;
            text-transform: uppercase; color: #334155;
        }

        .kicker {
            margin-top: 9mm;
            font-size: 11px; letter-spacing: 6px; text-transform: uppercase; color: #0d9488;
            font-weight: bold;
        }

        .presented { margin-top: 7mm; font-size: 11px; color: #64748b; font-style: italic; }

        /*
         * A serif for the name. dompdf ships Times among its core fonts, so it
         * needs no embedding, and a certificate set entirely in the UI's sans
         * reads like a receipt — this is the one line on the page that is meant
         * to be looked at rather than read.
         */
        .name {
            margin-top: 3mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 42px; font-weight: bold; color: #0f172a;
            letter-spacing: 0.5px;
        }

        /* A rule with a diamond in the middle, built from three table cells so
           the line halves stay centred whatever the page width. */
        .divider { margin: 6mm auto 0 auto; width: 120mm; }
        .divider table { width: 100%; border-collapse: collapse; }
        .divider td { vertical-align: middle; }
        .divider .line { height: 1px; background: #cbd5e1; font-size: 0; line-height: 0; }
        .divider .gem { width: 14px; }
        .divider .gem div {
            width: 6px; height: 6px; margin: 0 auto;
            background: #0d9488; border-radius: 3px;
        }

        .for { margin-top: 6mm; font-size: 12px; color: #475569; }
        .course {
            margin-top: 3mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 24px; font-weight: bold; color: #0d9488;
        }

        /* ── The medal ──
           The score as a struck coin rather than a sentence. It is the only
           number on the page anybody is proud of, and "Scored 88.7%" in 11px
           grey was burying it. */
        .medal-wrap { margin-top: 10mm; }
        .medal {
            width: 33mm; height: 33mm; margin: 0 auto;
            border: 3px solid #0d9488; border-radius: 17mm;
            background: #f0fdfa;
        }
        .medal .pct {
            margin-top: 8.5mm;
            font-size: 23px; font-weight: bold; color: #0f5e59; line-height: 1;
        }
        .medal .word {
            margin-top: 1.5mm;
            font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #0f766e;
        }

        /* ── Signatures and dates ── */
        .signatures { position: fixed; left: 32mm; right: 32mm; bottom: 33mm; }
        .signatures table { width: 100%; border-collapse: collapse; }
        .signatures td { width: 50%; vertical-align: bottom; padding: 0 6mm; }
        .sig-line { border-top: 1px solid #94a3b8; padding-top: 2mm; }
        .sig-label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .sig-value { font-size: 11px; color: #1e293b; font-weight: bold; }

        /* ── The small print ── */
        .footer { position: fixed; left: 32mm; right: 32mm; bottom: 16mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { font-size: 8px; color: #94a3b8; vertical-align: bottom; }
        .footer td.r { text-align: right; }
        .serial {
            font-family: 'Courier', monospace; font-size: 9px;
            color: #334155; letter-spacing: 1px;
        }
        .expired { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>

{{-- Decoration first, and all of it fixed. Nothing below this point can push
     the page over. --}}
<div class="edge"></div>
<div class="edge-inner"></div>
<div class="corner corner-tl"></div>
<div class="corner corner-tr"></div>
<div class="corner corner-bl"></div>
<div class="corner corner-br"></div>

<div class="body">
    @php
        // dataUri, not url(): dompdf would need remote fetching switched on and
        // the ability to reach the app's own public URL to render a link. See
        // App\Services\Branding\CompanyLogo.
        $logo    = app(\App\Services\Branding\CompanyLogo::class)->dataUri($company?->id);
        $percent = rtrim(rtrim(number_format((float) $certificate->percent, 1), '0'), '.');
    @endphp

    @if ($logo)
        <img src="{{ $logo }}" class="logo" alt="">
    @else
        <div class="company">{{ $company->brand_name ?? $company->name ?? '' }}</div>
    @endif

    <div class="kicker">Certificate of Completion</div>

    <div class="presented">This is to certify that</div>

    {{-- The FROZEN name and title, not the live relations. A certificate is a
         statement about a moment. See App\Models\TrainingCertificate. --}}
    <div class="name">{{ $certificate->recipient_name }}</div>

    <div class="divider">
        <table>
            <tr>
                <td class="line"></td>
                <td class="gem"><div></div></td>
                <td class="line"></td>
            </tr>
        </table>
    </div>

    <div class="for">has successfully completed</div>
    <div class="course">{{ $certificate->title }}</div>

    <div class="medal-wrap">
        <div class="medal">
            <div class="pct">{{ $percent }}%</div>
            <div class="word">Scored</div>
        </div>
    </div>
</div>

{{-- Two columns that a person actually looks for on a certificate: when it was
     earned, and who says so. The expiry sits under the date rather than in the
     small print — on a compliance course it is the fact that decides whether
     the piece of paper is still worth anything. --}}
<div class="signatures">
    <table>
        <tr>
            <td>
                <div class="sig-line">
                    <div class="sig-value">{{ $certificate->issued_at?->format('j F Y') }}</div>
                    <div class="sig-label">
                        @if ($certificate->expires_on)
                            {{ $certificate->expires_on->isPast() ? 'Expired' : 'Valid until' }}
                            {{ $certificate->expires_on->format('j F Y') }}
                        @else
                            Date of completion
                        @endif
                    </div>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <div class="sig-value">{{ $company->brand_name ?? $company->name ?? 'Management' }}</div>
                    <div class="sig-label">Issued by</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    <table>
        <tr>
            <td>
                <div class="serial">{{ $certificate->serial }}</div>
                Quote this serial to verify the certificate
            </td>
            <td class="r">
                Issued through {{ config('app.name', 'Servora') }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
