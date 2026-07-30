{{--
    One printable label document — every physical label is one page.

    CONSTRAINT: this view is rendered BOTH by Chrome (browser driver, kiosk
    printing) and by dompdf (archive / reprint). It must stay inside dompdf's
    CSS subset — absolute positioning, basic fonts, no flexbox, no grid.
    Anything outside that renders in Chrome and silently vanishes from the
    PDF, which would make the designer preview a lie.

    The ONE exception is $rotate, which uses a CSS transform dompdf cannot
    do. That is safe only because rotation is never passed to the PDF path:
    it corrects for a printer whose driver insists on portrait media, and an
    archived label should read the natural way up regardless. See
    LabelRenderService::pdf().

    @page carries the physical page size so Chrome prints at exact scale.
    The printer driver's page size must match, or the output silently scales
    and clips — hence the calibration label.
--}}
@php
    // When rotating, the PAGE is portrait and the LABEL is turned to fit it.
    $pageW = $rotate ? $heightMm : $widthMm;
    $pageH = $rotate ? $widthMm : $heightMm;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        @page {
            size: {{ $pageW }}mm {{ $pageH }}mm;
            margin: 0;
        }

        /* Borders and padding must never widen an element past the page.
           Anything exceeding the page makes Chrome paginate, and each extra
           page is a wasted physical label. */
        * { box-sizing: border-box; }

        /* Width only, and NO overflow:hidden here. This document is
           multi-page — one page per label — and hiding body overflow can
           suppress every page after the first. Each page clips itself. */
        html, body {
            margin: 0;
            padding: 0;
            width: {{ $pageW }}mm;
        }

        .page {
            position: relative;
            width: {{ $pageW }}mm;
            height: {{ $pageH }}mm;
            overflow: hidden;
            page-break-after: always;
        }

        /* No trailing break after the final label — it would feed a blank. */
        .page.last {
            page-break-after: auto;
        }

        .label {
            position: absolute;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
@if ($rotate)
            /* Rotate clockwise about the top-left, then push right by the
               page width so the label lands back inside the page. */
            left: {{ $pageW }}mm;
            top: 0;
            transform: rotate(90deg);
            transform-origin: 0 0;
@else
            left: 0;
            top: 0;
@endif
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
    <div class="page{{ $page['last'] ? ' last' : '' }}">
        <div class="label">
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
    </div>
@endforeach
</body>
</html>
