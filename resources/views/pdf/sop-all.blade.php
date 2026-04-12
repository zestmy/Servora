<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($company->brand_name ?? $company->name) }} - Training SOPs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 22mm 20mm 20mm 20mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; color: #1f2937; line-height: 1.5; }
        .page-break { page-break-before: always; }

        /* Cover */
        .cover-wrap { text-align: center; padding-top: 90px; }
        .cover-confidential { display: inline-block; border: 2px solid #dc2626; color: #dc2626; padding: 5px 22px; font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 35px; }
        .cover-brand { font-size: 30pt; font-weight: bold; color: #0f172a; letter-spacing: -0.8px; margin-bottom: 6px; }
        .cover-subbrand { font-size: 12pt; color: #64748b; margin-bottom: 4px; }
        .cover-reg { font-size: 10pt; color: #94a3b8; }
        .cover-accent-wrap { margin: 34px 0 20px; }
        .cover-accent { display: inline-block; width: 60px; height: 3px; background: #0f172a; }
        .cover-title { font-size: 16pt; font-weight: bold; color: #0f172a; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 6px; }
        .cover-subtitle { font-size: 11pt; color: #64748b; letter-spacing: 1.5px; text-transform: uppercase; }
        .cover-meta { font-size: 10pt; color: #475569; margin-top: 45px; line-height: 1.7; }
        .cover-disclaimer { margin-top: 55px; padding: 14px 50px; font-size: 9pt; color: #64748b; font-style: italic; line-height: 1.7; }

        /* TOC */
        .toc-header { font-size: 18pt; font-weight: bold; color: #0f172a; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 18px; padding-bottom: 8px; border-bottom: 3px solid #0f172a; }
        .toc-category { font-size: 12pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; margin-top: 16px; margin-bottom: 8px; padding: 7px 12px; background: #f1f5f9; border-left: 4px solid #0f172a; }
        .toc-item { padding: 6px 12px; border-bottom: 1px dotted #cbd5e1; font-size: 11pt; color: #1f2937; }
        .toc-num { display: inline-block; width: 30px; color: #64748b; font-weight: bold; font-size: 10pt; }
        .toc-code { color: #94a3b8; font-size: 9pt; font-family: 'Courier New', monospace; }

        /* Document header */
        table.doc-header { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.doc-header td { vertical-align: middle; padding: 0; }
        .dh-logo { width: 90px; padding-right: 16px; text-align: left; vertical-align: middle; }
        .dh-logo img { max-height: 70px; max-width: 85px; display: block; }
        .dh-body { padding: 0; vertical-align: middle; }
        .dh-brand { font-size: 13pt; font-weight: bold; color: #1f2937; letter-spacing: -0.2px; line-height: 1.2; }
        .dh-company { font-size: 9pt; color: #6b7280; letter-spacing: 0.2px; margin-top: 1px; }
        .dh-divider { width: 44px; height: 3px; background: #0f172a; margin: 7px 0; }
        .dh-recipe { font-size: 20pt; font-weight: bold; color: #0f172a; letter-spacing: -0.6px; line-height: 1.1; margin-bottom: 3px; }
        .dh-subtitle { font-size: 9pt; color: #475569; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; }
        .dh-description { font-size: 10pt; color: #475569; font-style: italic; margin-top: 5px; line-height: 1.5; }
        .header-rule { height: 1px; background: #cbd5e1; margin-bottom: 16px; }

        /* Hero */
        table.hero { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.hero > tbody > tr > td { vertical-align: top; padding: 0; }
        .hero-photo-cell { width: 42%; padding-right: 12px; vertical-align: middle !important; }
        .hero-photo-frame { border: 1px solid #e2e8f0; background: #f8fafc; padding: 4px; text-align: center; }
        .hero-photo-frame img { max-width: 100%; height: auto; max-height: 230px; display: block; margin: 0 auto; }
        .hero-photo-frame .no-photo { color: #94a3b8; font-size: 10pt; padding: 60px 10px; font-style: italic; }
        table.hero-info { width: 100%; border-collapse: collapse; }
        table.hero-info td { padding: 9px 12px; font-size: 10.5pt; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
        table.hero-info td.label { width: 90px; font-weight: bold; color: #475569; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.8px; border-right: 1px solid #e2e8f0; background: #f8fafc; }
        table.hero-info tr:first-child td { border-top: 2px solid #0f172a; }
        table.hero-info tr:last-child td { border-bottom: 2px solid #0f172a; }
        table.hero-info .big-value { font-size: 12pt; font-weight: bold; color: #0f172a; }

        /* Section header */
        .section-header { font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 2.5px; color: #0f172a; margin: 0 0 9px; padding-bottom: 5px; border-bottom: 2px solid #0f172a; }

        /* Linear steps */
        table.step-list { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.step-list td { padding: 7px 0; vertical-align: top; }
        table.step-list td.num-cell { width: 34px; padding-right: 12px; text-align: center; }
        table.step-list td.num-cell .num { display: inline-block; width: 24px; height: 24px; background: #0f172a; color: #fff; text-align: center; line-height: 24px; font-size: 10.5pt; font-weight: bold; border-radius: 50%; }
        table.step-list .step-title-inline { font-size: 11.5pt; font-weight: bold; color: #0f172a; display: block; line-height: 1.3; }
        table.step-list .step-body { font-size: 10.5pt; color: #1f2937; line-height: 1.55; margin-top: 3px; }

        /* Step cards */
        .step-card { border: 1px solid #e2e8f0; background: #fff; }
        .step-card .step-img { width: 100%; height: 140px; display: block; border-bottom: 1px solid #e2e8f0; }
        .step-card .step-no-img { width: 100%; height: 140px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
        .step-card-body { padding: 9px 11px; }
        .step-card .step-num { display: inline-block; background: #0f172a; color: #fff; min-width: 20px; text-align: center; padding: 2px 7px; font-size: 9.5pt; font-weight: bold; margin-right: 5px; }
        .step-card .step-title { font-size: 10.5pt; font-weight: bold; color: #0f172a; }
        .step-card .step-text { font-size: 10pt; color: #334155; line-height: 1.5; margin-top: 4px; }

        /* Ingredients list */
        .ing-list { font-size: 10pt; line-height: 1.7; color: #1f2937; }
        .ing-list strong { color: #0f172a; font-weight: bold; }
        .ing-sep { color: #cbd5e1; margin: 0 4px; }

        /* Plating */
        .plating-label { font-size: 9.5pt; font-weight: bold; text-transform: uppercase; color: #475569; letter-spacing: 1.8px; margin-bottom: 5px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; }
        .plating-img { max-width: 100%; height: auto; max-height: 220px; border: 1px solid #e2e8f0; }

        /* QR */
        .qr-img { width: 75px; height: 75px; border: 1px solid #cbd5e1; padding: 3px; background: #fff; }
        .qr-label { font-size: 7.5pt; color: #475569; font-weight: bold; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; }

        /* Warning */
        .warning-notice { margin-top: 14px; padding: 10px 14px; border-top: 1px solid #cbd5e1; font-size: 8.5pt; color: #64748b; line-height: 1.6; font-style: italic; }
        .warning-notice strong { color: #0f172a; font-style: normal; letter-spacing: 0.5px; }

        /* Footer */
        .pdf-footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 8pt; color: #94a3b8; }
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
        <img src="{{ $logoBase64 }}" style="max-height: 75px; max-width: 220px; margin-bottom: 22px;" />
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

        {{-- Header: Logo + Brand + Recipe Name --}}
        <table class="doc-header">
            <tr>
                @if ($logoBase64)
                    <td class="dh-logo"><img src="{{ $logoBase64 }}" /></td>
                @endif
                <td class="dh-body">
                    <div class="dh-brand">{{ $company->brand_name ?? $company->name }}</div>
                    @if ($company->brand_name && $company->name !== $company->brand_name)
                        <div class="dh-company">{{ $company->name }}</div>
                    @endif
                    <div class="dh-divider"></div>
                    <div class="dh-recipe">{{ strtoupper($recipe->name) }}</div>
                    <div class="dh-subtitle">
                        @if ($recipe->category){{ $recipe->category }} · @endif
                        Standard Operating Procedure
                    </div>
                    @if ($recipe->description)
                        <div class="dh-description">{{ $recipe->description }}</div>
                    @endif
                </td>
            </tr>
        </table>
        <div class="header-rule"></div>

        {{-- Hero --}}
        <table class="hero">
            <tr>
                <td class="hero-photo-cell">
                    <div class="hero-photo-frame">
                        @if ($heroImage)
                            <img src="{{ $heroImage }}" />
                        @else
                            <div class="no-photo">No presentation photo</div>
                        @endif
                    </div>
                </td>
                <td>
                    <table class="hero-info">
                        <tr>
                            <td class="label">Yield</td>
                            <td><span class="big-value">{{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</span></td>
                        </tr>
                        @if ($recipe->category)
                            <tr><td class="label">Category</td><td>{{ $recipe->category }}</td></tr>
                        @endif
                        @if ($recipe->lines->count())
                            <tr>
                                <td class="label">Ingredients</td>
                                <td>
                                    <div class="ing-list">
                                        @foreach ($recipe->lines as $line)
                                            <strong>{{ $line->ingredient?->name ?? '—' }}</strong>: {{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }} {{ $line->uom?->abbreviation ?? '' }}@if (! $loop->last)<span class="ing-sep">·</span>@endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                        @if ($qr)
                            <tr>
                                <td class="label">Training</td>
                                <td>
                                    <table style="border: none; border-collapse: collapse;">
                                        <tr>
                                            <td style="border: none; padding: 0; width: 85px;">
                                                <img class="qr-img" src="{{ $qr }}" />
                                                <div class="qr-label">Scan for Video</div>
                                            </td>
                                            <td style="border: none; padding: 0 0 0 12px; vertical-align: middle; font-size: 9.5pt; color: #475569;">
                                                Scan the QR code with your phone to watch the training video.
                                            </td>
                                        </tr>
                                    </table>
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
                <table style="width: 100%; border-collapse: separate; border-spacing: 7px 7px;">
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
            <div style="margin-top: 16px;">
                <div class="section-header">Plating Presentation</div>
                <table style="width: 100%; border-collapse: separate; border-spacing: 7px;">
                    <tr>
                        @if (! empty($remainingDineIn))
                            <td style="width: {{ ! empty($remainingTakeaway) ? '50%' : '100%' }}; vertical-align: top;">
                                <div class="plating-label">Dine-In</div>
                                @foreach ($remainingDineIn as $b64)
                                    <div style="margin-bottom: 4px;"><img src="{{ $b64 }}" class="plating-img" /></div>
                                @endforeach
                            </td>
                        @endif
                        @if (! empty($remainingTakeaway))
                            <td style="width: {{ ! empty($remainingDineIn) ? '50%' : '100%' }}; vertical-align: top;">
                                <div class="plating-label">Takeaway</div>
                                @foreach ($remainingTakeaway as $b64)
                                    <div style="margin-bottom: 4px;"><img src="{{ $b64 }}" class="plating-img" /></div>
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
