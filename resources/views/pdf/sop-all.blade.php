<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($company->brand_name ?? $company->name) }} - Training SOPs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 20mm 15mm 18mm 15mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; color: #1f2937; line-height: 1.45; }
        .page-break { page-break-before: always; }

        /* Cover */
        .cover-wrap { text-align: center; padding-top: 90px; }
        .cover-confidential { display: inline-block; border: 2px solid #dc2626; color: #dc2626; padding: 5px 20px; font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 35px; }
        .cover-brand { font-size: 28pt; font-weight: bold; color: #000; letter-spacing: -0.5px; margin-bottom: 5px; }
        .cover-subbrand { font-size: 12pt; color: #555; margin-bottom: 3px; }
        .cover-reg { font-size: 10pt; color: #777; }
        .cover-accent-wrap { margin: 30px 0 18px; }
        .cover-accent { display: inline-block; width: 60px; height: 3px; background: #000; }
        .cover-title { font-size: 16pt; font-weight: bold; color: #000; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 5px; }
        .cover-subtitle { font-size: 11pt; color: #555; letter-spacing: 1px; text-transform: uppercase; }
        .cover-meta { font-size: 10pt; color: #555; margin-top: 40px; line-height: 1.6; }
        .cover-disclaimer { margin-top: 50px; padding: 12px 40px; font-size: 9pt; color: #666; font-style: italic; line-height: 1.7; }

        /* TOC */
        .toc-header { font-size: 16pt; font-weight: bold; color: #000; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 3px solid #000; }
        .toc-category { font-size: 12pt; font-weight: bold; color: #000; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 12px; margin-bottom: 6px; padding: 5px 10px; background: #f3f4f6; border-left: 4px solid #000; }
        .toc-item { padding: 5px 10px; border-bottom: 1px dotted #cbd5e1; font-size: 11pt; color: #1f2937; }
        .toc-num { display: inline-block; width: 28px; color: #6b7280; font-weight: bold; font-size: 10pt; }
        .toc-code { color: #9ca3af; font-size: 9pt; font-family: 'Courier New', monospace; }

        /* Document header */
        table.doc-header { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 10px; }
        table.doc-header td { vertical-align: middle; padding: 0; }
        .dh-logo-cell { width: 75px; text-align: center; border-right: 1px solid #000; padding: 5px; }
        .dh-logo-cell img { max-height: 50px; max-width: 65px; }
        .dh-title-cell { border-right: 1px solid #000; padding: 0; }
        .dh-title-main { font-size: 13pt; font-weight: bold; color: #000; padding: 5px 10px 2px; letter-spacing: 0.3px; }
        .dh-title-sub { font-size: 10pt; color: #333; font-style: italic; padding: 0 10px 4px; }
        .dh-dept { font-size: 10pt; font-weight: bold; color: #000; padding: 3px 10px; background: #f3f4f6; border-top: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.5px; }
        .dh-meta-cell { width: 140px; padding: 0; }
        table.dh-meta { width: 100%; border-collapse: collapse; }
        table.dh-meta td { padding: 3px 8px; font-size: 9pt; border-bottom: 1px solid #e5e7eb; }
        table.dh-meta tr:last-child td { border-bottom: none; }
        table.dh-meta tr.hl td { background: #f3f4f6; }

        /* Hero */
        table.hero { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 10px; }
        table.hero > tbody > tr > td { vertical-align: top; padding: 0; }
        .hero-photo-cell { width: 38%; text-align: center; border-right: 1px solid #000; padding: 5px; background: #fafafa; vertical-align: middle !important; }
        .hero-photo-cell img { max-width: 100%; height: auto; max-height: 200px; }
        .hero-photo-cell .no-photo { color: #9ca3af; font-size: 10pt; padding: 30px 10px; font-style: italic; }
        table.hero-info { width: 100%; border-collapse: collapse; }
        table.hero-info td { padding: 5px 10px; font-size: 10.5pt; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        table.hero-info td.label { width: 85px; font-weight: bold; background: #f3f4f6; border-right: 1px solid #e5e7eb; color: #111; }
        table.hero-info tr:last-child td { border-bottom: none; }

        /* Section header */
        .section-header { font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #000; margin: 0 0 6px; padding-bottom: 3px; border-bottom: 2px solid #000; }

        /* Linear steps */
        table.step-list { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.step-list td { padding: 5px 0; vertical-align: top; }
        table.step-list td.num-cell { width: 30px; padding-right: 8px; text-align: center; }
        table.step-list td.num-cell .num {
            display: inline-block; width: 20px; height: 20px;
            background: #000; color: #fff;
            text-align: center; line-height: 20px;
            font-size: 10pt; font-weight: bold; border-radius: 50%;
        }
        table.step-list .step-title-inline { font-size: 11pt; font-weight: bold; color: #000; display: block; line-height: 1.3; }
        table.step-list .step-body { font-size: 10.5pt; color: #1f2937; line-height: 1.5; margin-top: 2px; }

        /* Step cards */
        .step-card { border: 1px solid #000; background: #fff; }
        .step-card .step-img { width: 100%; height: 140px; display: block; border-bottom: 1px solid #000; }
        .step-card .step-no-img { width: 100%; height: 140px; background: #f3f4f6; border-bottom: 1px solid #000; }
        .step-card-body { padding: 6px 9px; }
        .step-card .step-num { display: inline-block; background: #000; color: #fff; min-width: 18px; text-align: center; padding: 1px 6px; font-size: 9.5pt; font-weight: bold; margin-right: 4px; }
        .step-card .step-title { font-size: 10.5pt; font-weight: bold; color: #000; }
        .step-card .step-text { font-size: 10pt; color: #1f2937; line-height: 1.45; margin-top: 3px; }

        /* Ingredients table */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #000; }
        table.items thead th { background: #1f2937; color: #fff; padding: 5px 7px; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.7px; text-align: left; font-weight: bold; }
        table.items thead th.right { text-align: right; }
        table.items tbody td { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; font-size: 10pt; color: #1f2937; }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        table.items tbody td.right { text-align: right; }

        /* Plating */
        .plating-label { font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #000; letter-spacing: 1px; margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px solid #000; }
        .plating-img { max-width: 100%; height: auto; max-height: 200px; border: 1px solid #d1d5db; }

        /* QR */
        .qr-img { width: 70px; height: 70px; border: 1px solid #000; padding: 2px; }
        .qr-label { font-size: 8pt; color: #000; font-weight: bold; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; }

        /* Warning */
        .warning-notice { margin-top: 10px; padding: 6px 10px; border-top: 2px solid #000; font-size: 8pt; color: #374151; line-height: 1.45; }
        .warning-notice strong { color: #000; }

        /* Footer (inline — dompdf friendly) */
        .pdf-footer { margin-top: 20px; padding-top: 6px; border-top: 1px solid #9ca3af; font-size: 8pt; color: #6b7280; }
        .pdf-footer .left { float: left; }
        .pdf-footer .right { float: right; }
    </style>
</head>
<body>

{{-- ═══ COVER ═══ --}}
<div class="cover-wrap">
    <div class="cover-confidential">Private &amp; Confidential</div>
    <div style="margin-top: 30px;"></div>
    @if ($logoBase64)
        <img src="{{ $logoBase64 }}" style="max-height: 65px; max-width: 200px; margin-bottom: 20px;" />
    @endif
    <div class="cover-brand">{{ $brandName }}</div>
    @if ($company->brand_name && $company->name !== $company->brand_name)
        <div class="cover-subbrand">{{ $company->name }}</div>
    @endif
    @if ($company->registration_number)
        <div class="cover-reg">Registration: {{ $company->registration_number }}</div>
    @endif
    <div class="cover-accent-wrap"><span class="cover-accent"></span></div>
    <div class="cover-title">Standard Operating Procedures</div>
    <div class="cover-subtitle">Training Manual</div>
    <div class="cover-meta">
        <strong>Generated:</strong> {{ now()->format('d F Y') }}<br>
        <strong>Exported by:</strong> {{ $exportedBy }}
    </div>
    <div class="cover-disclaimer">
        This manual is confidential and the sole property of {{ $brandName }}.<br>
        Unauthorised reproduction, distribution, or disclosure is strictly prohibited.
    </div>
</div>

{{-- ═══ TOC ═══ --}}
<div class="page-break"></div>
<div class="toc-header">Table of Contents</div>
@php $sopNumber = 0; @endphp
@foreach ($grouped as $categoryName => $catRecipes)
    <div class="toc-category">{{ $categoryName }}</div>
    @foreach ($catRecipes as $recipe)
        @php $sopNumber++; @endphp
        <div class="toc-item">
            <span class="toc-num">{{ $sopNumber }}.</span>
            {{ $recipe->name }}
            @if ($recipe->code)
                <span class="toc-code">· {{ $recipe->code }}</span>
            @endif
        </div>
    @endforeach
@endforeach

{{-- ═══ SOP PAGES ═══ --}}
@php $sopNumber = 0; @endphp
@foreach ($grouped as $categoryName => $catRecipes)
    @foreach ($catRecipes as $recipe)
        @php
            $sopNumber++;
            $stepImgs = $recipeStepImages[$recipe->id] ?? [];
            $hasStepImgs = !empty($stepImgs);
            $imgs = $recipeImages[$recipe->id] ?? ['dine_in' => [], 'takeaway' => []];
            $heroImage = $imgs['dine_in'][0] ?? $imgs['takeaway'][0] ?? null;
            $qr = $recipeQrs[$recipe->id] ?? null;
            $remainingDineIn = count($imgs['dine_in']) > 1 ? array_slice($imgs['dine_in'], 1) : [];
            $remainingTakeaway = (empty($imgs['dine_in']) && count($imgs['takeaway']) > 1)
                ? array_slice($imgs['takeaway'], 1)
                : (! empty($imgs['dine_in']) ? $imgs['takeaway'] : []);
        @endphp
        <div class="page-break"></div>

        {{-- Header --}}
        <table class="doc-header">
            <tr>
                <td class="dh-logo-cell">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" />
                    @else
                        <div style="font-size: 8pt; font-weight: bold;">{{ $brandName }}</div>
                    @endif
                </td>
                <td class="dh-title-cell">
                    <div class="dh-title-main">{{ strtoupper($recipe->name) }}</div>
                    @if ($recipe->description)
                        <div class="dh-title-sub">{{ $recipe->description }}</div>
                    @endif
                    <div class="dh-dept">
                        @if ($recipe->category){{ $recipe->category }} · @endif
                        SOP #{{ $sopNumber }}
                    </div>
                </td>
                <td class="dh-meta-cell">
                    <table class="dh-meta">
                        <tr><td><strong>Doc. No:</strong> {{ $recipe->code ?? '—' }}</td></tr>
                        <tr><td><strong>Rev. No:</strong> {{ $recipe->updated_at?->format('ymd') }}</td></tr>
                        <tr class="hl"><td><strong>Date:</strong> {{ now()->format('d M Y') }}</td></tr>
                        <tr class="hl"><td><strong>Category:</strong> {{ $categoryName }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Hero --}}
        <table class="hero">
            <tr>
                <td class="hero-photo-cell">
                    @if ($heroImage)
                        <img src="{{ $heroImage }}" />
                    @else
                        <div class="no-photo">No presentation photo</div>
                    @endif
                </td>
                <td>
                    <table class="hero-info">
                        <tr>
                            <td class="label">Yield</td>
                            <td><strong style="font-size: 11.5pt;">{{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</strong></td>
                        </tr>
                        @if ($recipe->category)
                            <tr><td class="label">Category</td><td>{{ $recipe->category }}</td></tr>
                        @endif
                        @if ($recipe->lines->count())
                            <tr>
                                <td class="label">Ingredients</td>
                                <td>
                                    @foreach ($recipe->lines as $line)
                                        <strong>{{ $line->ingredient?->name ?? '—' }}</strong>: {{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }} {{ $line->uom?->abbreviation ?? '' }}@if (! $loop->last) · @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        @if ($qr)
                            <tr>
                                <td class="label">Training</td>
                                <td>
                                    <table style="border: none;"><tr>
                                        <td style="border: none; padding: 0; width: 80px;">
                                            <img class="qr-img" src="{{ $qr }}" />
                                            <div class="qr-label">Scan for Video</div>
                                        </td>
                                        <td style="border: none; padding: 0 0 0 10px; vertical-align: middle; font-size: 9.5pt; color: #374151;">
                                            Scan the QR code with your phone to watch the training video.
                                        </td>
                                    </tr></table>
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        {{-- Steps --}}
        @if ($recipe->steps->count())
            <div class="section-header">Preparation Steps</div>
            @if ($hasStepImgs)
                <table style="width: 100%; border-collapse: separate; border-spacing: 5px 5px;">
                    @foreach ($recipe->steps->chunk(3) as $row)
                        <tr>
                            @foreach ($row as $step)
                                <td style="width: 33.33%; vertical-align: top; padding: 0;">
                                    <div class="step-card">
                                        @if (isset($stepImgs[$step->id]))
                                            <img class="step-img" src="{{ $stepImgs[$step->id] }}" />
                                        @else
                                            <div class="step-no-img"></div>
                                        @endif
                                        <div class="step-card-body">
                                            <div style="margin-bottom: 2px;">
                                                <span class="step-num">{{ $step->sort_order + 1 }}</span>
                                                <span class="step-title">{{ $step->title ?: 'Step ' . ($step->sort_order + 1) }}</span>
                                            </div>
                                            <div class="step-text">{!! nl2br(e($step->instruction)) !!}</div>
                                        </div>
                                    </div>
                                </td>
                            @endforeach
                            @for ($i = $row->count(); $i < 3; $i++)
                                <td style="width: 33.33%;"></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @else
                <table class="step-list">
                    @foreach ($recipe->steps as $step)
                        <tr>
                            <td class="num-cell"><span class="num">{{ $step->sort_order + 1 }}</span></td>
                            <td>
                                @if ($step->title)
                                    <span class="step-title-inline">{{ $step->title }}</span>
                                @endif
                                <div class="step-body">{!! nl2br(e($step->instruction)) !!}</div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        @endif

        {{-- Additional plating --}}
        @if (! empty($remainingDineIn) || ! empty($remainingTakeaway))
            <div style="margin-top: 12px;">
                <div class="section-header">Plating Presentation</div>
                <table style="width: 100%; border-collapse: separate; border-spacing: 5px;">
                    <tr>
                        @if (! empty($remainingDineIn))
                            <td style="width: {{ ! empty($remainingTakeaway) ? '50%' : '100%' }}; vertical-align: top;">
                                <div class="plating-label">Dine-In</div>
                                @foreach ($remainingDineIn as $b64)
                                    <div style="margin-bottom: 3px;"><img src="{{ $b64 }}" class="plating-img" /></div>
                                @endforeach
                            </td>
                        @endif
                        @if (! empty($remainingTakeaway))
                            <td style="width: {{ ! empty($remainingDineIn) ? '50%' : '100%' }}; vertical-align: top;">
                                <div class="plating-label">Takeaway</div>
                                @foreach ($remainingTakeaway as $b64)
                                    <div style="margin-bottom: 3px;"><img src="{{ $b64 }}" class="plating-img" /></div>
                                @endforeach
                            </td>
                        @endif
                    </tr>
                </table>
            </div>
        @endif

        <div class="warning-notice">
            <strong>WARNING:</strong> This document is the property of {{ $company->brand_name ?? $company->name }} and temporary possession and access is granted only to authorised personnel. No part of this document shall be reproduced, copied, duplicated or extracted using any form without prior written permission from the property owner.
        </div>

    @endforeach
@endforeach

<div class="pdf-footer">
    <span class="left">&copy; {{ now()->format('Y') }} {{ $brandName }} &middot; Confidential &amp; proprietary</span>
    <span class="right">Generated {{ now()->format('d M Y') }} · {{ $exportedBy }} &middot; Powered by Servora</span>
</div>

</body>
</html>
