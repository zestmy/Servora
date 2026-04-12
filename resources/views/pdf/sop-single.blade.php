@extends('pdf.layout')

@section('title', 'SOP - ' . $recipe->name)

@section('content')
    {{-- ═══ Header: Logo + Brand + Recipe Name ═══════════════════ --}}
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

    {{-- ═══ Hero: presentation photo + key info ═════════════════ --}}
    @php
        $heroImage = $dineInBase64[0] ?? $takeawayBase64[0] ?? null;
    @endphp
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
                        <tr>
                            <td class="label">Category</td>
                            <td>{{ $recipe->category }}</td>
                        </tr>
                    @endif
                    @if ($recipe->lines->count())
                        <tr>
                            <td class="label">Ingredients</td>
                            <td>
                                <table class="ing-table">
                                    @foreach ($recipe->lines as $line)
                                        <tr>
                                            <td class="ing-bullet">&bull;</td>
                                            <td class="ing-name">{{ $line->ingredient?->name ?? '—' }}</td>
                                            <td class="ing-qty">{{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }} {{ $line->uom?->abbreviation ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                    @if ($videoQr)
                        <tr>
                            <td class="label">Training</td>
                            <td>
                                <table style="border: none; border-collapse: collapse;">
                                    <tr>
                                        <td style="border: none; padding: 0; width: 90px;">
                                            <img class="qr-img" src="{{ $videoQr }}" />
                                            <div class="qr-label">Scan for Video</div>
                                        </td>
                                        <td style="border: none; padding: 0 0 0 12px; vertical-align: middle; font-size: 10pt; color: #475569;">
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

    @php $hasStepImages = !empty($stepImagesBase64 ?? []); @endphp

    {{-- ═══ Preparation Steps ═══════════════════════════════════ --}}
    @if ($recipe->steps->count())
        <div class="section-header">Preparation Steps</div>

        @if ($hasStepImages)
            <table style="width: 100%; border-collapse: separate; border-spacing: 8px 8px;">
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
                                    <div class="step-card-body">
                                        <div style="margin-bottom: 3px;">
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

    {{-- ═══ Additional plating images ═══════════════════════════ --}}
    @php
        $remainingDineIn = count($dineInBase64) > 1 ? array_slice($dineInBase64, 1) : [];
        $remainingTakeaway = (empty($dineInBase64) && count($takeawayBase64) > 1)
            ? array_slice($takeawayBase64, 1)
            : (! empty($dineInBase64) ? $takeawayBase64 : []);
    @endphp
    @if (! empty($remainingDineIn) || ! empty($remainingTakeaway))
        <div style="margin-top: 18px;">
            <div class="section-header">Plating Presentation</div>
            <table style="width: 100%; border-collapse: separate; border-spacing: 8px;">
                <tr>
                    @if (! empty($remainingDineIn))
                        <td style="width: {{ ! empty($remainingTakeaway) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Dine-In</div>
                            @foreach ($remainingDineIn as $b64)
                                <div style="margin-bottom: 5px;"><img src="{{ $b64 }}" class="plating-img" /></div>
                            @endforeach
                        </td>
                    @endif
                    @if (! empty($remainingTakeaway))
                        <td style="width: {{ ! empty($remainingDineIn) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Takeaway</div>
                            @foreach ($remainingTakeaway as $b64)
                                <div style="margin-bottom: 5px;"><img src="{{ $b64 }}" class="plating-img" /></div>
                            @endforeach
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- ═══ Warning notice ══════════════════════════════════════ --}}
    <div class="warning-notice">
        <strong>WARNING:</strong> This document is the property of {{ $company->brand_name ?? $company->name }} and temporary possession and access is granted only to authorised personnel. No part of this document shall be reproduced, copied, duplicated or extracted using any form without prior written permission from the property owner.
    </div>

@endsection
