{{--
    Calibration label — a ruler, not a label.

    Printed once per printer to measure what the hardware actually swallows.
    Every thermal printer has an unprintable margin that shifts with media,
    driver and how the roll is seated, so this measures it rather than
    assuming a figure from a spec sheet.

    How to read it: the outer border sits flush to the declared edge. If a
    side of the border is missing, that edge is being clipped. The ruler
    numbers show by how much — if the top ruler starts at 3 rather than 0,
    the printer is losing 3mm on the left, and 3 goes in the X offset.

    Browser-print only, so it may use CSS the dompdf path avoids.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Calibration</title>
    <style>
        @page { size: {{ $widthMm }}mm {{ $heightMm }}mm; margin: 0; }

        /* Without this the frame's 0.3mm border is ADDED to its 70mm width,
           making it 70.6mm on a 70mm page. Chrome then paginates and the
           print run eats extra labels. */
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            overflow: hidden;
        }

        .label {
            position: relative;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            overflow: hidden;
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
        }

        /* Flush to the declared edge: any missing side is a clipped side. */
        .frame {
            position: absolute;
            left: 0; top: 0;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            border: 0.3mm solid #000;
        }

        .tick   { position: absolute; background: #000; }
        .tick-h { width: 0.2mm; }
        .tick-v { height: 0.2mm; }
        .num    { position: absolute; font-size: 4pt; }
        .corner { position: absolute; background: #000; }
        .info   { position: absolute; font-size: 6pt; text-align: center; }
    </style>
</head>
<body>
<div class="label">
    <div class="frame"></div>

    {{-- Top ruler: 5mm major ticks, 1mm minor.
         Stops BEFORE the far edge — a tick at left:70mm on a 70mm page sits
         outside it and forces an extra page. The frame marks that edge. --}}
    @for ($mm = 0; $mm < $widthMm; $mm++)
        @php $major = $mm % 5 === 0; @endphp
        <div class="tick tick-h" style="left: {{ $mm }}mm; top: 0; height: {{ $major ? 2 : 1 }}mm;"></div>
        @if ($major && $mm > 0 && $mm < $widthMm)
            <div class="num" style="left: {{ $mm - 1.5 }}mm; top: 2.2mm; width: 3mm; text-align: center;">{{ $mm }}</div>
        @endif
    @endfor

    {{-- Left ruler. Same reason it stops short of the bottom edge. --}}
    @for ($mm = 0; $mm < $heightMm; $mm++)
        @php $major = $mm % 5 === 0; @endphp
        <div class="tick tick-v" style="top: {{ $mm }}mm; left: 0; width: {{ $major ? 2 : 1 }}mm;"></div>
        @if ($major && $mm > 0 && $mm < $heightMm)
            <div class="num" style="top: {{ $mm - 0.9 }}mm; left: 2.2mm;">{{ $mm }}</div>
        @endif
    @endfor

    {{-- Corner marks at exactly 1mm in from each corner. A corner that
         doesn't print tells you which side is short. --}}
    @foreach ([[1, 1], [$widthMm - 4, 1], [1, $heightMm - 1.3], [$widthMm - 4, $heightMm - 1.3]] as [$cx, $cy])
        <div class="corner" style="left: {{ $cx }}mm; top: {{ $cy }}mm; width: 3mm; height: 0.3mm;"></div>
    @endforeach

    <div class="info" style="left: 0; top: {{ $heightMm / 2 - 4 }}mm; width: {{ $widthMm }}mm;">
        <div style="font-size: 8pt; font-weight: bold;">{{ $widthMm }} × {{ $heightMm }} mm</div>
        <div style="margin-top: 0.8mm;">{{ $printerName }}</div>
        <div style="margin-top: 0.5mm;">Border clipped? Enter the missing mm as an offset.</div>
    </div>
</div>
</body>
</html>
