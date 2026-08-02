{{--
    Print-set QR labels.

    The page is declared in EXPLICIT MILLIMETRES matching the media loaded in
    the printer, never a CSS keyword. "A6" and "4x6 inch" are both sold as
    airway-bill stock and they differ in both size and aspect ratio, so a
    page declared as one and printed on the other gets rotated and shrunk to
    fit — which produced a postage-stamp label in the corner of a blank one.

    Single-label sizes fill the page with one set. A4 falls back to a grid of
    cut-out cards for setting up a whole outlet on ordinary paper.
--}}
@php
    $page   = $sizes[$size];
    $single = $page['mode'] === 'single';

    // Margin and QR scale to the page so a new size needs no new numbers.
    $margin = $single ? 5.0 : 12.0;
    $inner  = $page['w'] - ($margin * 2);
    $qrSize = $single ? round($inner * 0.72, 1) : 45.0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Label set QR codes — {{ $outlet->name }}</title>
    <style>
        @page {
            size: {{ $page['w'] }}mm {{ $page['h'] }}mm;
            margin: 0;   /* the card supplies its own inset */
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 6mm;
            max-width: 210mm;
            margin: 0 auto;
        }

        .toolbar h1 { font-size: 14pt; margin: 0; }
        .toolbar p  { font-size: 9pt; color: #6b7280; margin: 2px 0 0; }

        .btn {
            border: 0;
            background: #0b7677;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 10pt;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-ghost { background: #fff; color: #0b7677; border: 1px solid #aeeae4; }
        .btn-ghost.on { background: #0b7677; color: #fff; }

        .sheet { margin: 0 auto; }

@if ($single)
        .sheet { width: {{ $page['w'] }}mm; }

        .card {
            width: {{ $page['w'] }}mm;
            height: {{ $page['h'] }}mm;
            padding: {{ $margin }}mm;
            text-align: center;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* One label per page. */
            break-after: page;
            page-break-after: always;
            overflow: hidden;
        }

        .card:last-child { break-after: auto; page-break-after: auto; }

        .card img { width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; }
        .name   { font-size: 20pt; }
        .outlet { font-size: 11pt; }
        .hint   { font-size: 10pt; }

        .storage       { border: 1.2mm solid #111827; padding: 2.5mm 2mm; margin: 2mm 0 3mm; }
        .storage-state { font-size: 15pt; }
        .storage-temp  { font-size: 22pt; }
@else
        .sheet { width: {{ $page['w'] }}mm; padding: {{ $margin }}mm; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }

        .card {
            border: 1px dashed #9ca3af;   /* the cut line */
            border-radius: 3mm;
            padding: 6mm;
            text-align: center;
            background: #fff;
            /* Half a QR code is useless. */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .card img { width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; }
        .name   { font-size: 13pt; }
        .outlet { font-size: 9pt; }
        .hint   { font-size: 8pt; }

        .storage       { border: 0.8mm solid #111827; padding: 1.5mm 1mm; margin: 1.5mm 0 2mm; }
        .storage-state { font-size: 10pt; }
        .storage-temp  { font-size: 14pt; }
@endif

        .card img { display: block; margin: 0 auto 3mm; }
        .name    { font-weight: bold; margin: 0 0 1mm; }
        .outlet  { color: #6b7280; margin: 0 0 2mm; }
        .hint    { color: #4b5563; margin: 0; }

        /* Pure black and heavy: read across a kitchen, and it may come off a
           monochrome printer onto matte label stock. */
        .storage-state,
        .storage-temp {
            font-weight: bold;
            color: #000;
            margin: 0;
            line-height: 1.15;
            letter-spacing: .02em;
        }

        .powered { font-size: 7pt; color: #9ca3af; margin: 3mm 0 0; letter-spacing: .02em; }
        .sheet-footer { text-align: center; margin-top: 6mm; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; @if (! $single) padding: {{ $margin }}mm; @endif }
            .card { background: #fff; margin: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div>
        <h1>Print set QR codes</h1>
        <p>{{ $outlet->name }} —
            @if ($single)
                one set per label, peel and stick where it's prepped.
            @else
                cut along the dashed lines.
            @endif
        </p>
        {{-- Printing the browser page means negotiating page size and scale
             with the driver, which is where this kept going wrong. The PDF
             carries its own page box and sidesteps all of it. --}}
        <p style="color:#b45309">
            <strong>Use Download PDF</strong> and print that at Actual size — it is
            exactly {{ rtrim(rtrim(number_format($page['w'], 1), '0'), '.') }} ×
            {{ rtrim(rtrim(number_format($page['h'], 1), '0'), '.') }}mm.
            Printing this page from the browser instead means setting Paper size to
            your stock, Margins to None and Scale to 100, and the driver may still
            rescale it.
        </p>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        @foreach ($sizes as $key => $option)
            <a class="btn btn-ghost {{ $key === $size ? 'on' : '' }}"
               href="{{ route('labels.sets.qr-sheet', array_filter([
                    'outlet' => $outlet->id,
                    'set'    => request()->query('set'),
                    'size'   => $key,
               ])) }}">{{ $option['label'] }}</a>
        @endforeach
        <a class="btn" href="{{ route('labels.sets.qr-sheet', array_filter([
                'outlet' => $outlet->id,
                'set'    => request()->query('set'),
                'size'   => $size,
                'format' => 'pdf',
           ])) }}">Download PDF</a>
        <button class="btn btn-ghost" onclick="window.print()">Print page</button>
    </div>
</div>

<div class="sheet">
    @unless ($single) <div class="grid"> @endunless

    @foreach ($cards as $card)
        <div class="card">
            <img src="{{ $card['image'] }}" alt="QR code for {{ $card['set']->name }}">
            <p class="name">{{ $card['set']->name }}</p>
            <p class="outlet">{{ $outlet->name }}</p>

            @if (count($card['storages']))
                <div class="storage">
                    @foreach ($card['storages'] as $storage)
                        <p class="storage-state">{{ strtoupper($storage['label']) }}</p>
                        @if ($storage['temperature'])
                            <p class="storage-temp">{{ $storage['temperature'] }}</p>
                        @endif
                    @endforeach
                </div>
            @endif

            <p class="hint">Scan to print this set</p>
            @if ($single)
                <p class="powered">Powered by Servora</p>
            @endif
        </div>
    @endforeach

    @unless ($single)
        </div>
        <div class="sheet-footer">
            <p class="powered">Powered by Servora</p>
        </div>
    @endunless
</div>
</body>
</html>
