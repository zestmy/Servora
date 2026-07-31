{{--
    Print-set QR codes.

    Two sizes, because they serve different jobs:

    A6 (105 × 148mm) is the default — airway-bill label stock, one set per
    label, peel and stick straight onto the chiller door. No scissors, and
    the code comes out around 70mm so it scans across a kitchen.

    A4 is the bulk option: four cut-out cards to a page for someone setting
    up a whole outlet at once on ordinary paper.
--}}
@php
    $isA6 = $size === 'a6';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Label set QR codes — {{ $outlet->name }}</title>
    <style>
        @page {
            size: {{ $isA6 ? 'A6' : 'A4' }};
            margin: {{ $isA6 ? '5mm' : '12mm' }};
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        .sheet {
            max-width: {{ $isA6 ? '95mm' : '190mm' }};
            margin: 0 auto;
            padding: 8mm 0;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 6mm;
            max-width: 190mm;
            margin-left: auto;
            margin-right: auto;
        }

        .toolbar h1 { font-size: 14pt; margin: 0; }
        .toolbar p  { font-size: 9pt; color: #6b7280; margin: 2px 0 0; }

        .btn {
            border: 0;
            background: #4f46e5;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 10pt;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-ghost {
            background: #fff;
            color: #4f46e5;
            border: 1px solid #c7d2fe;
        }

@if ($isA6)
        /* One label per page. No cut line — this is peel-and-stick stock. */
        .card {
            width: 95mm;
            height: 138mm;
            padding: 6mm;
            text-align: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 2mm;
            margin: 0 auto 6mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            break-after: page;
            page-break-after: always;
        }

        .card:last-child { break-after: auto; page-break-after: auto; }

        .card img { width: 70mm; height: 70mm; }
        .name   { font-size: 20pt; }
        .outlet { font-size: 11pt; }
        .count  { font-size: 9pt; }
        .hint   { font-size: 10pt; }
@else
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6mm;
        }

        .card {
            border: 1px dashed #9ca3af;   /* the cut line */
            border-radius: 3mm;
            padding: 6mm;
            text-align: center;
            background: #fff;
            /* Never split a card across two pages — half a QR is useless. */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .card img { width: 45mm; height: 45mm; }
        .name   { font-size: 13pt; }
        .outlet { font-size: 9pt; }
        .count  { font-size: 8pt; }
        .hint   { font-size: 8pt; }
@endif

        .card img { display: block; margin: 0 auto 3mm; }
        .name    { font-weight: bold; margin: 0 0 1mm; }
        .outlet  { color: #6b7280; margin: 0 0 2mm; }
        .count   { color: #9ca3af; margin: 0 0 2mm; }
        .hint    { color: #4b5563; margin: 0; }

        .powered {
            font-size: 7pt;
            color: #9ca3af;
            margin: 3mm 0 0;
            letter-spacing: .02em;
        }

        .sheet-footer {
            text-align: center;
            margin-top: 6mm;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; padding: 0; }
            .card {
                background: #fff;
                margin: 0 auto;
                /* The page box already provides the margin. */
                border: {{ $isA6 ? 'none' : '1px dashed #9ca3af' }};
            }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div>
        <h1>Print set QR codes</h1>
        <p>
            {{ $outlet->name }} —
            @if ($isA6)
                one per A6 airway-bill label. Peel and stick where the set is prepped.
            @else
                cut along the dashed lines and fix each one where the set is prepped.
            @endif
        </p>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <a class="btn btn-ghost"
           href="{{ route('labels.sets.qr-sheet', array_filter([
                'outlet' => $outlet->id,
                'set'    => request()->query('set'),
                'size'   => $isA6 ? 'a4' : 'a6',
           ])) }}">{{ $isA6 ? 'A4 sheet' : 'A6 labels' }}</a>
        <button class="btn" onclick="window.print()">Print</button>
    </div>
</div>

<div class="sheet">
    @unless ($isA6) <div class="grid"> @endunless

    @foreach ($cards as $card)
        <div class="card">
            <img src="{{ $card['image'] }}" alt="QR code for {{ $card['set']->name }}">
            <p class="name">{{ $card['set']->name }}</p>
            <p class="outlet">{{ $outlet->name }}</p>
            <p class="count">
                {{ $card['set']->lines_count }} item{{ $card['set']->lines_count === 1 ? '' : 's' }}
            </p>
            <p class="hint">Scan to print this set</p>
            {{-- A6 is one label per page, so the credit belongs on the label
                 itself. On A4 it goes once at the foot of the sheet instead
                 of being repeated on every cut-out card. --}}
            @if ($isA6)
                <p class="powered">Powered by Servora</p>
            @endif
        </div>
    @endforeach

    @unless ($isA6)
        </div>
        <div class="sheet-footer">
            <p class="powered">Powered by Servora</p>
        </div>
    @endunless
</div>
</body>
</html>
