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
         * render as nothing. Teal is brand-600 (#0d9488); the muted gold
         * (#b08d3e) exists only on this document — it is certificate regalia,
         * not a product colour, which is why it appears in no token scale.
         * If the brand scale in tailwind.config.js moves, this file is edited
         * by hand — the trade the design system's "never hardcode brand hex"
         * rule calls out as unavoidable in PDF templates.
         */
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1a1a1a; }

        /*
         * EVERYTHING ON THIS SHEET IS position:fixed, which in dompdf means
         * "relative to the page". Two reasons, both learned the hard way:
         *
         * 1. Nothing in normal flow can push a sub-millimetre past the page
         *    edge and print a blank second page — the old `height: 210mm`
         *    sheet did exactly that.
         * 2. dompdf paints the normal flow FIRST and fixed elements after it,
         *    so anything in flow would be painted UNDER the opaque paper
         *    texture. Inside the fixed layer, document order decides: paper
         *    first, ink after.
         */

        /* The sheet itself: a felt-paper texture pinned to the full page. */
        .paper {
            position: fixed; top: 0; left: 0;
            width: 297mm; height: 210mm;
        }

        /* ── The frame ──
           A classic double rule: a strong outer band, a hairline inside it,
           and gold corner marks bridging the two. No transforms — dompdf
           renders them unreliably — so the corners are L-shapes drawn as two
           borders on empty boxes. */
        .edge {
            position: fixed; top: 7mm; left: 7mm; right: 7mm; bottom: 7mm;
            border: 2.5px solid #0d9488;
        }
        .edge-inner {
            position: fixed; top: 10.5mm; left: 10.5mm; right: 10.5mm; bottom: 10.5mm;
            border: 1px solid #5eead4;
        }
        .corner { position: fixed; width: 12mm; height: 12mm; }
        .corner-tl { top: 13mm; left: 13mm; border-top: 2px solid #b08d3e; border-left: 2px solid #b08d3e; }
        .corner-tr { top: 13mm; right: 13mm; border-top: 2px solid #b08d3e; border-right: 2px solid #b08d3e; }
        .corner-bl { bottom: 13mm; left: 13mm; border-bottom: 2px solid #b08d3e; border-left: 2px solid #b08d3e; }
        .corner-br { bottom: 13mm; right: 13mm; border-bottom: 2px solid #b08d3e; border-right: 2px solid #b08d3e; }

        .body { position: fixed; top: 0; left: 0; right: 0; padding: 15mm 30mm 0 30mm; text-align: center; }

        .logo { max-height: 60px; max-width: 240px; }
        .company {
            font-size: 13px; font-weight: bold; letter-spacing: 3px;
            text-transform: uppercase; color: #334155;
        }

        /* ── The masthead ──
           The document says what it is in the largest type on the page after
           the name. Times, because dompdf ships it as a core font and a
           certificate set entirely in the UI sans reads like a receipt. */
        .display {
            margin-top: 7mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 34px; letter-spacing: 12px; color: #0f172a;
        }
        .display-sub {
            margin-top: 2mm;
            font-size: 10px; letter-spacing: 7px; text-transform: uppercase;
            color: #0d9488; font-weight: bold;
        }

        .presented { margin-top: 8mm; font-size: 11px; color: #64748b; font-style: italic; }

        .name {
            margin-top: 3mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 44px; font-weight: bold; color: #0f172a;
            letter-spacing: 0.5px;
        }

        /* A rule with a gold diamond at its centre, built from three table
           cells so the halves stay centred whatever the page width. */
        .divider { margin: 5mm auto 0 auto; width: 110mm; }
        .divider table { width: 100%; border-collapse: collapse; }
        .divider td { vertical-align: middle; }
        .divider .line { height: 1px; background: #cbd5e1; font-size: 0; line-height: 0; }
        .divider .gem { width: 14px; }
        .divider .gem div {
            width: 6px; height: 6px; margin: 0 auto;
            background: #b08d3e; border-radius: 3px;
        }

        .for { margin-top: 5mm; font-size: 12px; color: #475569; }
        .course {
            margin-top: 2.5mm;
            font-family: 'Times New Roman', Times, serif;
            font-size: 25px; font-weight: bold; color: #0d9488;
        }

        /* ── The seal ──
           The score struck as a gold seal: a double ring, which is the whole
           difference between "a circle with a number" and regalia. It is the
           only number on the page anybody is proud of. */
        .seal-wrap { margin-top: 8mm; }
        .seal {
            width: 32mm; height: 32mm; margin: 0 auto;
            border: 2.5px solid #b08d3e; border-radius: 16mm;
            background: #fdfaf1;
        }
        .seal-inner {
            width: 27mm; height: 27mm; margin: 2.2mm auto 0 auto;
            border: 1px solid #cbb26a; border-radius: 13.5mm;
        }
        .seal .pct {
            margin-top: 7mm;
            font-size: 22px; font-weight: bold; color: #8a6a2a; line-height: 1;
        }
        .seal .word {
            margin-top: 1.5mm;
            font-size: 7.5px; letter-spacing: 2.5px; text-transform: uppercase; color: #a3823f;
        }

        /* ── The closing row: date | verification | signatory ──
           The three things a person holding the paper looks for, in the
           order a western reader scans them. Cells are bottom-aligned so the
           two rule lines sit level however tall the signature image is. */
        .closing { position: fixed; left: 26mm; right: 26mm; bottom: 24mm; }
        .closing table { width: 100%; border-collapse: collapse; }
        .closing td { vertical-align: bottom; }
        .closing .cell-date, .closing .cell-sig { width: 40%; padding: 0 4mm; }
        .closing .cell-qr { width: 20%; text-align: center; }

        .rule-line { border-top: 1px solid #94a3b8; padding-top: 2mm; }
        .rule-label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .rule-value { font-size: 11.5px; color: #1e293b; font-weight: bold; }
        .rule-extra { margin-top: 0.8mm; font-size: 8.5px; color: #475569; }

        .signature-img { max-height: 42px; max-width: 52mm; margin-bottom: 1.5mm; }

        .qr-img { width: 17mm; height: 17mm; }
        .qr-caption {
            margin-top: 1mm;
            font-size: 6.5px; letter-spacing: 1.8px; text-transform: uppercase; color: #64748b;
        }

        /* ── The small print ── */
        .footer { position: fixed; left: 26mm; right: 26mm; bottom: 13.5mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { font-size: 7.5px; color: #94a3b8; vertical-align: bottom; }
        .footer td.r { text-align: right; }
        .serial {
            font-family: 'Courier', monospace; font-size: 8.5px;
            color: #334155; letter-spacing: 1px;
        }
    </style>
</head>
<body>

@php
    /*
     * Everything the sheet embeds, resolved up front. All of it is data URIs:
     * dompdf's remote fetching is off, so a URL would render as a blank.
     */

    // The felt-paper sheet. Guarded — a certificate must never fail to issue
    // over a missing decoration.
    $paperFile = public_path('images/certificate-paper.jpg');
    $paper     = is_file($paperFile)
        ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($paperFile))
        : null;

    $logo    = app(\App\Services\Branding\CompanyLogo::class)->dataUri($company?->id);
    $percent = rtrim(rtrim(number_format((float) $certificate->percent, 1), '0'), '.');

    // The verification QR. LabelQrService's PNG encoder is deliberately
    // generic — same library, same reasoning (dompdf's SVG support is patchy;
    // a raster QR is unambiguous).
    $qr = app(\App\Services\Labels\LabelQrService::class)->encodePng($certificate->verifyUrl());

    // The signatory, straight off the company row. Any of it may be absent;
    // an unconfigured company closes with "Issued by {brand}" as it always has.
    $signatoryName    = $company?->cert_signatory_name;
    $signatoryTitle   = $company?->cert_signatory_title;
    $signatoryCompany = $company?->cert_signatory_company;

    $signature = null;
    if ($company?->cert_signature_path) {
        $signatureFile = \Illuminate\Support\Facades\Storage::disk('public')->path($company->cert_signature_path);
        if (is_file($signatureFile)) {
            $signature = 'data:' . (mime_content_type($signatureFile) ?: 'image/png')
                . ';base64,' . base64_encode(file_get_contents($signatureFile));
        }
    }
@endphp

{{-- Paper first, ink after — see the stylesheet's fixed-layer note. --}}
@if ($paper)
    <img src="{{ $paper }}" class="paper" alt="">
@endif
<div class="edge"></div>
<div class="edge-inner"></div>
<div class="corner corner-tl"></div>
<div class="corner corner-tr"></div>
<div class="corner corner-bl"></div>
<div class="corner corner-br"></div>

<div class="body">
    @if ($logo)
        <img src="{{ $logo }}" class="logo" alt="">
    @else
        <div class="company">{{ $company->brand_name ?? $company->name ?? '' }}</div>
    @endif

    <div class="display">CERTIFICATE</div>
    <div class="display-sub">of Completion</div>

    <div class="presented">This is to proudly certify that</div>

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

    <div class="seal-wrap">
        <div class="seal">
            <div class="seal-inner">
                <div class="pct">{{ $percent }}%</div>
                <div class="word">Scored</div>
            </div>
        </div>
    </div>
</div>

{{-- Date on the left, the authenticity QR in the middle, the hand that signs
     on the right. The expiry sits under the date rather than in the small
     print — on a compliance course it is the fact that decides whether the
     piece of paper is still worth anything. --}}
<div class="closing">
    <table>
        <tr>
            <td class="cell-date">
                <div class="rule-line">
                    <div class="rule-value">{{ $certificate->issued_at?->format('j F Y') }}</div>
                    <div class="rule-label">
                        @if ($certificate->expires_on)
                            {{ $certificate->expires_on->isPast() ? 'Expired' : 'Valid until' }}
                            {{ $certificate->expires_on->format('j F Y') }}
                        @else
                            Date of completion
                        @endif
                    </div>
                </div>
            </td>
            <td class="cell-qr">
                <img src="{{ $qr }}" class="qr-img" alt="">
                <div class="qr-caption">Scan to verify</div>
            </td>
            <td class="cell-sig">
                @if ($signature)
                    <img src="{{ $signature }}" class="signature-img" alt="">
                @endif
                <div class="rule-line">
                    @if ($signatoryName)
                        <div class="rule-value">{{ $signatoryName }}</div>
                        @if ($signatoryTitle)
                            <div class="rule-extra">{{ $signatoryTitle }}</div>
                        @endif
                        <div class="rule-label">{{ $signatoryCompany ?: ($company->brand_name ?? $company->name ?? '') }}</div>
                    @else
                        <div class="rule-value">{{ $signatoryCompany ?: ($company->brand_name ?? $company->name ?? 'Management') }}</div>
                        <div class="rule-label">Issued by</div>
                    @endif
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
                Scan the code or quote this serial to verify
            </td>
            <td class="r">
                Issued through {{ config('app.name', 'Servora') }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>
