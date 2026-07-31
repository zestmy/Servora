{{--
    PDF version of the QR labels.

    Exists because HTML @page printing kept losing the argument with the
    printer driver: the browser renegotiates page size, orientation and
    scale at print time, and a label came out rotated and a third of its
    size however precisely the page was declared. A PDF carries its own
    MediaBox, so "Actual size" means actual size — the same route the
    70x40 item labels already take, and those print correctly.

    CONSTRAINT: rendered by dompdf, so it must stay inside dompdf's CSS
    subset. No flexbox, no grid, no transforms. Vertical placement is done
    with plain margins rather than centring tricks, and the QR is a PNG
    because dompdf's SVG handling is unreliable.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;  /* has °, − and friends */
            color: #000;
        }

        /*
         * No width or height here on purpose. The PDF page box already IS
         * the label, so stating the size again — and having padding added
         * on top of it, since box-sizing is not to be relied on in dompdf —
         * pushed the card past the page and produced a blank second label
         * for every one printed.
         */
        .card {
            padding: {{ $margin }}mm;
            text-align: center;
            page-break-after: always;
        }

        .card.last { page-break-after: auto; }


        .name    { font-size: 19pt; font-weight: bold; margin: 3mm 0 1mm; }
        .outlet  { font-size: 10pt; color: #555; margin: 0 0 3mm; }
        .hint    { font-size: 9pt; color: #444; margin: 3mm 0 0; }
        .powered { font-size: 7pt; color: #999; margin: 2mm 0 0; }

        .storage {
            border: 1.2mm solid #000;
            padding: 2.5mm 2mm;
            margin: 0 0 1mm;
        }

        .storage-state { font-size: 14pt; font-weight: bold; margin: 0; }
        .storage-temp  { font-size: 21pt; font-weight: bold; margin: 0; }
    </style>
</head>
<body>
@foreach ($cards as $i => $card)
    <div class="card{{ $i === count($cards) - 1 ? ' last' : '' }}">
        <img src="{{ $card["png"] }}" alt=""
             style="width: {{ $card["qr_size"] }}mm; height: {{ $card["qr_size"] }}mm;">
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
        <p class="powered">Powered by Servora</p>
    </div>
@endforeach
</body>
</html>
