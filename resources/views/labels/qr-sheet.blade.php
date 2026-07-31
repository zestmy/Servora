{{--
    Cut-out QR cards for print sets.

    Sized so four fit an A4 page with room to cut between them, and so the
    code itself stays around 45mm square — big enough to scan from arm's
    length on a chiller door under kitchen lighting, which is the whole
    point of putting it there.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Label set QR codes — {{ $outlet->name }}</title>
    <style>
        @page { size: A4; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        .sheet {
            max-width: 190mm;
            margin: 0 auto;
            padding: 8mm 0;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6mm;
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
        }

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

        .card img {
            width: 45mm;
            height: 45mm;
            display: block;
            margin: 0 auto 3mm;
        }

        .name    { font-size: 13pt; font-weight: bold; margin: 0 0 1mm; }
        .outlet  { font-size: 9pt; color: #6b7280; margin: 0 0 2mm; }
        .count   { font-size: 8pt; color: #9ca3af; margin: 0 0 2mm; }
        .hint    { font-size: 8pt; color: #4b5563; margin: 0; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; padding: 0; }
            .card { background: #fff; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="toolbar">
        <div>
            <h1>Print set QR codes</h1>
            <p>{{ $outlet->name }} — cut along the dashed lines and fix each one where the set is prepped.</p>
        </div>
        <button class="btn" onclick="window.print()">Print</button>
    </div>

    <div class="grid">
        @foreach ($cards as $card)
            <div class="card">
                <img src="{{ $card['image'] }}" alt="QR code for {{ $card['set']->name }}">
                <p class="name">{{ $card['set']->name }}</p>
                <p class="outlet">{{ $outlet->name }}</p>
                <p class="count">
                    {{ $card['set']->lines_count }} item{{ $card['set']->lines_count === 1 ? '' : 's' }}
                </p>
                <p class="hint">Scan to print this set</p>
            </div>
        @endforeach
    </div>
</div>
</body>
</html>
