@extends('pdf.layout')

@section('title', 'SOP - ' . $recipe->name)

@section('content')
    {{-- ── Header ───────────────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="company-logo" />
            @endif
            <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
            @if ($company->brand_name && $company->name !== $company->brand_name)
                <div class="company-detail">{{ $company->name }}</div>
            @endif
            @if ($company->registration_number)
                <div class="company-detail">Reg: {{ $company->registration_number }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-confidential">Private &amp; Confidential</div><br>
            <div class="doc-badge">SOP</div>
            <div class="doc-title">Standard Operating Procedure</div>
            @if ($recipe->code)
                <div class="doc-number">{{ $recipe->code }}</div>
            @endif
        </div>
    </div>

    {{-- ── Recipe Hero ─────────────────────────────────── --}}
    <div class="recipe-hero">
        <div class="recipe-hero-left">
            <div class="recipe-name">{{ $recipe->name }}</div>
            <div class="recipe-meta">
                @if ($recipe->category)
                    <span class="pill">{{ strtoupper($recipe->category) }}</span>
                @endif
                <strong>Yield:</strong> {{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}
                @if ($recipe->lines->count())
                    &nbsp;&middot;&nbsp; <strong>{{ $recipe->lines->count() }}</strong> ingredient{{ $recipe->lines->count() === 1 ? '' : 's' }}
                @endif
                @if ($recipe->steps->count())
                    &nbsp;&middot;&nbsp; <strong>{{ $recipe->steps->count() }}</strong> step{{ $recipe->steps->count() === 1 ? '' : 's' }}
                @endif
            </div>
            @if ($recipe->description)
                <div class="recipe-description">{{ $recipe->description }}</div>
            @endif
        </div>
        @if ($videoQr)
            <div class="recipe-hero-right">
                <div class="qr-box">
                    <img src="{{ $videoQr }}" />
                    <div class="qr-label">Scan for Video</div>
                </div>
            </div>
        @endif
    </div>

    @php $hasStepImages = !empty($stepImagesBase64 ?? []); @endphp

    {{-- ── Ingredients & Steps side by side (no images layout) ─── --}}
    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            {{-- Ingredients (left) --}}
            <td style="width: 42%; vertical-align: top; padding-right: 12px;">
                @if ($recipe->lines->count())
                    <div class="section-header">Ingredients</div>
                    <table class="items">
                        <thead>
                            <tr>
                                <th style="width: 18px;">#</th>
                                <th>Ingredient</th>
                                <th class="right" style="width: 50px;">Qty</th>
                                <th style="width: 40px;">UOM</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recipe->lines as $idx => $line)
                                <tr>
                                    <td style="color: #9ca3af;">{{ $idx + 1 }}</td>
                                    <td style="font-weight: 500;">{{ $line->ingredient?->name ?? '—' }}</td>
                                    <td class="right">{{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }}</td>
                                    <td style="color: #6b7280;">{{ $line->uom?->abbreviation ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>

            {{-- Preparation Steps (right) — only if NO images --}}
            <td style="width: 58%; vertical-align: top; padding-left: 12px; border-left: 1px solid #e5e7eb;">
                @if ($recipe->steps->count() && ! $hasStepImages)
                    <div class="section-header">Preparation Steps</div>
                    @foreach ($recipe->steps as $step)
                        <div class="step-item">
                            <span class="num">{{ $step->sort_order + 1 }}</span>
                            @if ($step->title)
                                <span class="step-title-inline">{{ $step->title }}</span>
                            @endif
                            <div class="step-body">{!! nl2br(e($step->instruction)) !!}</div>
                        </div>
                    @endforeach
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Preparation Steps — 3-column grid when images exist ── --}}
    @if ($recipe->steps->count() && $hasStepImages)
        <div style="margin-top: 10px; margin-bottom: 10px;">
            <div class="section-header">Preparation Steps</div>
            <table style="width: 100%; border-collapse: separate; border-spacing: 5px;">
                @foreach ($recipe->steps->chunk(3) as $row)
                    <tr>
                        @foreach ($row as $step)
                            <td style="width: 33.33%; vertical-align: top; padding: 0;">
                                <div class="step-card">
                                    @if (isset($stepImagesBase64[$step->id]))
                                        <img src="{{ $stepImagesBase64[$step->id] }}" style="width: 100%; height: 110px; object-fit: cover; display: block;" />
                                    @endif
                                    <div style="padding: 6px 8px;">
                                        <div>
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
        </div>
    @endif

    {{-- ── Plating Presentation ─────────────────────────── --}}
    @if (count($dineInBase64) || count($takeawayBase64))
        <div style="margin-top: 10px;">
            <div class="section-header">Plating Presentation</div>
            <table style="width: 100%; border-collapse: separate; border-spacing: 6px;">
                <tr>
                    @if (count($dineInBase64))
                        <td style="width: {{ count($takeawayBase64) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Dine-In</div>
                            @foreach ($dineInBase64 as $b64)
                                <div style="margin-bottom: 4px;">
                                    <img src="{{ $b64 }}" class="plating-img" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                    @if (count($takeawayBase64))
                        <td style="width: {{ count($dineInBase64) ? '50%' : '100%' }}; vertical-align: top;">
                            <div class="plating-label">Takeaway</div>
                            @foreach ($takeawayBase64 as $b64)
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

@endsection
