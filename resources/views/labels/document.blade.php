{{--
    One printable label document — every physical label is one page.

    CONSTRAINT: this view is rendered BOTH by Chrome (browser driver, kiosk
    printing) and by dompdf (archive / reprint). It must stay inside dompdf's
    CSS subset — absolute positioning, basic fonts, no flexbox, no grid, no
    transforms. Anything outside that renders in Chrome and silently vanishes
    from the PDF, which would make the designer preview a lie.

    @page carries the physical label size so Chrome prints at exact scale.
    The printer driver's page size must match, or the output silently scales
    and clips — hence the calibration label.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        @page {
            size: {{ $widthMm }}mm {{ $heightMm }}mm;
            margin: 0;
        }

        /* Borders and padding must never widen an element past the page.
           Anything exceeding the page makes Chrome paginate, and each extra
           page is a wasted physical label. */
        * { box-sizing: border-box; }

        /* Width only, and NO overflow:hidden here. This document is
           multi-page — one page per label — and hiding body overflow can
           suppress every page after the first. Each .label clips itself. */
        html, body {
            margin: 0;
            padding: 0;
            width: {{ $widthMm }}mm;
        }

        .label {
            position: relative;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            overflow: hidden;
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            page-break-after: always;
        }

        /* No trailing break after the final label — it would feed a blank. */
        .label.last {
            page-break-after: auto;
        }

        .f {
            position: absolute;
            line-height: 1.15;
        }

        .f img {
            max-width: 100%;
            max-height: 100%;
        }
    </style>
</head>
<body>
@foreach ($pages as $page)
    <div class="label{{ $page['last'] ? ' last' : '' }}">
        @foreach ($page['fields'] as $field)
            <div class="f" style="{{ $field['style'] }}">
                @if ($field['is_image'])
                    <img src="{{ $field['text'] }}" alt="">
                @else
                    {{ $field['text'] }}
                @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
