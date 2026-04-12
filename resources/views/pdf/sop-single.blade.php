@extends('pdf.layout')

@section('title', 'SOP - ' . $recipe->name)

@section('content')
    {{-- ── Compact document header ──────────────────────── --}}
    <div class="header">
        <div class="header-logo">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" />
            @else
                <div style="font-size: 9pt; font-weight: bold; color: #111;">
                    {{ $company->brand_name ?? $company->name }}
                </div>
            @endif
        </div>
        <div class="header-title">
            <div class="recipe-title-main">{{ strtoupper($recipe->name) }}</div>
            @if ($recipe->description)
                <div class="recipe-title-sub">{{ $recipe->description }}</div>
            @endif
            <div class="dept-row">
                <div class="dept-cell">
                    @if ($recipe->category){{ $recipe->category }} &middot; @endif
                    Standard Operating Procedure
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-row"><strong>Doc. No:</strong> {{ $recipe->code ?? '—' }}</div>
            <div class="doc-row"><strong>Rev. No:</strong> {{ $recipe->updated_at?->format('ymd') }}</div>
            <div class="doc-row highlight"><strong>Date:</strong> {{ now()->format('d M Y') }}</div>
            <div class="doc-row highlight"><strong>Page:</strong> 1 of 1</div>
        </div>
    </div>

    {{-- ── Hero: photo + metadata table ────────────────── --}}
    @php
        $heroImage = $dineInBase64[0] ?? $takeawayBase64[0] ?? null;
    @endphp
    <div class="hero">
        <div class="hero-photo">
            @if ($heroImage)
                <img src="{{ $heroImage }}" />
            @else
                <div class="no-photo">No presentation photo</div>
            @endif
        </div>
        <div class="hero-info">
            <table>
                <tr>
                    <td class="label">Yield</td>
                    <td>
                        <strong style="font-size: 12pt;">{{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</strong>
                    </td>
                </tr>
                @if ($recipe->category)
                    <tr>
                        <td class="label">Category</td>
                        <td>{{ $recipe->category }}</td>
                    </tr>
                @endif
                @if ($recipe->lines->count())
                    <tr>
                        <td class="label">Ingredients</td>
                        <td>
                            @foreach ($recipe->lines as $idx => $line)
                                <strong>{{ $line->ingredient?->name ?? '—' }}</strong>: {{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }} {{ $line->uom?->abbreviation ?? '' }}{{ !$loop->last ? ' &middot; ' : '' }}
                            @endforeach
                        </td>
                    </tr>
                @endif
                @if ($videoQr)
                    <tr>
                        <td class="label">Training</td>
                        <td>
                            <table style="border: none;"><tr>
                                <td style="border: none; padding: 0; width: 85px;">
                                    <div class="qr-box">
                                        <img src="{{ $videoQr }}" />
                                        <div class="qr-label">Scan for Video</div>
                                    </div>
                                </td>
                                <td style="border: none; padding: 4px 0 0 10px; vertical-align: middle; font-size: 10pt; color: #374151;">
                                    Scan the QR code with your phone to watch the training video.
                                </td>
                            </tr></table>
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    @php $hasStepImages = !empty($stepImagesBase64 ?? []); @endphp

    {{-- ── Preparation Steps ──────────────────────────── --}}
    @if ($recipe->steps->count())
        <div class="section-header">Preparation Steps</div>

        @if ($hasStepImages)
            {{-- 3-column grid with LARGE images and readable text --}}
            <table style="width: 100%; border-collapse: separate; border-spacing: 6px 6px;">
                @foreach ($recipe->steps->chunk(3) as $row)
                    <tr>
                        @foreach ($row as $step)
                            <td style="width: 33.33%; vertical-align: top; padding: 0;">
                                <div class="step-card">
                                    @if (isset($stepImagesBase64[$step->id]))
                                        <img class="step-img" src="{{ $stepImagesBase64[$step->id] }}" />
                                    @else
                                        <div class="step-no-img"></div>
                                    @endif
                                    <div class="step-body-wrap">
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
                            <td style="width: 33.33%; border: none;"></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        @else
            {{-- Linear layout with large text (kitchen-readable) --}}
            <div>
                @foreach ($recipe->steps as $step)
                    <div class="step-item">
                        <span class="num">{{ $step->sort_order + 1 }}</span>
                        @if ($step->title)
                            <span class="step-title-inline">{{ $step->title }}</span>
                        @endif
                        <div class="step-body">{!! nl2br(e($step->instruction)) !!}</div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ── Additional Plating Presentation (if extra images beyond hero) ── --}}
    @php
        $remainingDineIn = count($dineInBase64) > 1 ? array_slice($dineInBase64, 1) : [];
        $remainingTakeaway = (empty($dineInBase64) && count($takeawayBase64) > 1)
            ? array_slice($takeawayBase64, 1)
            : (! empty($dineInBase64) ? $takeawayBase64 : []);
    @endphp
    @if (! empty($remainingDineIn) || ! empty($remainingTakeaway))
        <div style="margin-top: 14px;">
            <div class="section-header">Plating Presentation</div>
            <table style="width: 100%; border-collapse: separate; border-spacing: 6px;">
                <tr>
                    @if (! empty($remainingDineIn))
                        <td style="width: {{ ! empty($remainingTakeaway) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Dine-In</div>
                            @foreach ($remainingDineIn as $b64)
                                <div style="margin-bottom: 4px;">
                                    <img src="{{ $b64 }}" class="plating-img" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                    @if (! empty($remainingTakeaway))
                        <td style="width: {{ ! empty($remainingDineIn) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Takeaway</div>
                            @foreach ($remainingTakeaway as $b64)
                                <div style="margin-bottom: 4px;">
                                    <img src="{{ $b64 }}" class="plating-img" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- ── Warning notice (like reference SOP) ─────────── --}}
    <div class="warning-notice">
        <strong>WARNING:</strong> This document is the property of {{ $company->brand_name ?? $company->name }} and temporary possession and access is granted only to authorised personnel. No part of this document shall be reproduced, copied, duplicated or extracted using any form without prior written permission from the property owner.
    </div>

@endsection
