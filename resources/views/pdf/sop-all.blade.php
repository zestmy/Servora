<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($company->brand_name ?? $company->name) }} - Training SOPs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 0; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.4; }
        .container { padding: 24px 28px 60px; }
        .page-break { page-break-before: always; }

        /* Cover */
        .cover-wrap { text-align: center; padding-top: 100px; padding-bottom: 40px; }
        .cover-confidential {
            display: inline-block;
            border: 2px solid #dc2626;
            color: #dc2626;
            padding: 4px 18px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
            border-radius: 3px;
            margin-bottom: 30px;
        }
        .cover-brand {
            font-size: 32px;
            font-weight: bold;
            color: #111827;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }
        .cover-subbrand { font-size: 11px; color: #6b7280; margin-bottom: 3px; }
        .cover-reg { font-size: 9px; color: #9ca3af; }
        .cover-accent {
            display: inline-block;
            width: 50px;
            height: 3px;
            background: #4f46e5;
            margin: 25px 0 15px;
        }
        .cover-title {
            font-size: 18px;
            font-weight: bold;
            color: #4f46e5;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .cover-subtitle { font-size: 10px; color: #6b7280; letter-spacing: 1px; text-transform: uppercase; }
        .cover-meta { font-size: 8.5px; color: #9ca3af; margin-top: 35px; }
        .cover-disclaimer {
            margin-top: 40px;
            padding: 12px 30px;
            font-size: 8px;
            color: #6b7280;
            font-style: italic;
            line-height: 1.6;
        }

        /* TOC */
        .toc-wrap { padding-top: 20px; }
        .toc-header {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 3px solid #4f46e5;
        }
        .toc-category {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 10px;
            margin-bottom: 4px;
            padding: 4px 8px;
            background: #e0e7ff;
            border-left: 3px solid #4f46e5;
        }
        .toc-item {
            padding: 3px 8px;
            border-bottom: 1px dotted #d1d5db;
            font-size: 10px;
            color: #1f2937;
        }
        .toc-num {
            display: inline-block;
            width: 24px;
            color: #6b7280;
            font-weight: bold;
            font-size: 8px;
        }
        .toc-code { color: #9ca3af; font-size: 8px; font-family: 'Courier New', monospace; }

        /* SOP header */
        .sop-header {
            display: table; width: 100%;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 3px solid #4f46e5;
        }
        .sop-header-left { display: table-cell; vertical-align: middle; width: 55%; }
        .sop-header-right { display: table-cell; vertical-align: middle; width: 45%; text-align: right; }
        .company-logo { max-height: 32px; max-width: 130px; margin-bottom: 3px; }
        .company-name { font-size: 13px; font-weight: bold; color: #111827; letter-spacing: -0.2px; }
        .company-detail { font-size: 8px; color: #6b7280; line-height: 1.5; }
        .doc-confidential {
            display: inline-block;
            border: 1.5px solid #dc2626;
            color: #dc2626;
            padding: 2px 8px;
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-radius: 2px;
            margin-bottom: 3px;
        }
        .doc-badge {
            display: inline-block;
            background: #4f46e5;
            color: #fff;
            padding: 2px 8px;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-radius: 2px;
        }
        .doc-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }
        .doc-number { font-size: 9.5px; font-family: 'Courier New', monospace; color: #4b5563; margin-top: 1px; }
        .doc-meta { font-size: 7.5px; color: #9ca3af; margin-top: 1px; }

        /* Recipe hero */
        .recipe-hero {
            background: #f9fafb;
            border-left: 4px solid #4f46e5;
            padding: 8px 12px;
            margin-bottom: 10px;
            display: table;
            width: 100%;
        }
        .recipe-hero-left { display: table-cell; vertical-align: middle; }
        .recipe-hero-right { display: table-cell; vertical-align: middle; width: 80px; text-align: right; }
        .recipe-name { font-size: 16px; font-weight: bold; color: #111827; letter-spacing: -0.3px; margin-bottom: 2px; }
        .recipe-meta { font-size: 8px; color: #6b7280; }
        .recipe-meta .pill {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 1px 6px;
            border-radius: 9px;
            font-weight: bold;
            font-size: 7px;
            margin-right: 3px;
        }
        .recipe-description { font-size: 8px; color: #4b5563; margin-top: 3px; font-style: italic; }

        /* Section header */
        .section-header {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: #4f46e5;
            margin-bottom: 5px;
            padding-bottom: 2px;
            border-bottom: 1.5px solid #4f46e5;
        }

        /* Ingredients table */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.items thead th {
            background: #1f2937;
            color: #fff;
            padding: 4px 6px;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            text-align: left;
            font-weight: bold;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody td { padding: 3px 6px; border-bottom: 1px solid #f3f4f6; font-size: 8.5px; color: #1f2937; }
        table.items tbody tr:nth-child(even) td { background: #f9fafb; }
        table.items tbody td.right { text-align: right; }

        /* Steps linear */
        .step-item { margin-bottom: 5px; padding-left: 20px; position: relative; }
        .step-item .num {
            position: absolute; left: 0; top: 0;
            width: 15px; height: 15px;
            background: #4f46e5; color: #fff;
            text-align: center; line-height: 15px;
            font-size: 7.5px; font-weight: bold;
            border-radius: 8px;
        }
        .step-item .step-title-inline { font-size: 9px; font-weight: bold; color: #111827; display: block; line-height: 1.3; }
        .step-item .step-body { font-size: 8.5px; color: #374151; line-height: 1.5; margin-top: 1px; }

        /* Step cards */
        .step-card {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: #fff;
            overflow: hidden;
        }
        .step-card .step-num {
            display: inline-block;
            background: #4f46e5; color: #fff;
            width: 14px; height: 14px;
            text-align: center; line-height: 14px;
            font-size: 7px; font-weight: bold;
            border-radius: 7px;
            margin-right: 3px;
        }
        .step-card .step-title { font-size: 8px; font-weight: bold; color: #111827; }
        .step-card .step-text { font-size: 7px; color: #374151; line-height: 1.4; margin-top: 1px; }

        /* Plating */
        .plating-label { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #6b7280; letter-spacing: 0.8px; margin-bottom: 2px; }
        .plating-img { max-width: 100%; height: auto; max-height: 150px; border: 1px solid #e5e7eb; border-radius: 3px; }

        /* QR */
        .qr-box { text-align: center; }
        .qr-box img { width: 60px; height: 60px; border: 1px solid #e5e7eb; padding: 2px; background: #fff; }
        .qr-box .qr-label { font-size: 6px; color: #4f46e5; font-weight: bold; margin-top: 1px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Footer */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 8px 28px;
            border-top: 1px solid #e5e7eb;
            font-size: 7px; color: #9ca3af;
            background: #fff;
        }
        .footer-left { display: inline-block; }
        .footer-right { display: inline-block; float: right; }
    </style>
</head>
<body>
<div class="container">

    {{-- ═══ COVER PAGE ═══ --}}
    <div class="cover-wrap">
        <div class="cover-confidential">Private &amp; Confidential</div><br>

        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" style="max-height: 60px; max-width: 200px; margin-bottom: 18px;" />
        @endif
        <div class="cover-brand">{{ $brandName }}</div>
        @if ($company->brand_name && $company->name !== $company->brand_name)
            <div class="cover-subbrand">{{ $company->name }}</div>
        @endif
        @if ($company->registration_number)
            <div class="cover-reg">Registration: {{ $company->registration_number }}</div>
        @endif

        <div class="cover-accent"></div>

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

    {{-- ═══ TABLE OF CONTENTS ═══ --}}
    <div class="page-break"></div>
    <div class="toc-wrap">
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
                        <span class="toc-code">&middot; {{ $recipe->code }}</span>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>

    {{-- ═══ SOP PAGES ═══ --}}
    @php $sopNumber = 0; @endphp
    @foreach ($grouped as $categoryName => $catRecipes)
        @foreach ($catRecipes as $recipe)
            @php $sopNumber++; @endphp
            <div class="page-break"></div>

            {{-- Header --}}
            <div class="sop-header">
                <div class="sop-header-left">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" class="company-logo" />
                    @endif
                    <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
                    @if ($company->registration_number)
                        <div class="company-detail">Reg: {{ $company->registration_number }}</div>
                    @endif
                </div>
                <div class="sop-header-right">
                    <div class="doc-confidential">Private &amp; Confidential</div><br>
                    <div class="doc-badge">SOP</div>
                    <div class="doc-title">Standard Operating Procedure</div>
                    @if ($recipe->code)
                        <div class="doc-number">{{ $recipe->code }}</div>
                    @endif
                    <div class="doc-meta">#{{ $sopNumber }} &middot; {{ $categoryName }}</div>
                </div>
            </div>

            {{-- Recipe Hero --}}
            @php $qr = $recipeQrs[$recipe->id] ?? null; @endphp
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
                @if ($qr)
                    <div class="recipe-hero-right">
                        <div class="qr-box">
                            <img src="{{ $qr }}" />
                            <div class="qr-label">Scan for Video</div>
                        </div>
                    </div>
                @endif
            </div>

            @php $stepImgs = $recipeStepImages[$recipe->id] ?? []; $hasStepImgs = !empty($stepImgs); @endphp

            {{-- Ingredients & Steps --}}
            <table style="width: 100%; margin-bottom: 8px;">
                <tr>
                    <td style="width: 40%; vertical-align: top; padding-right: 10px;">
                        @if ($recipe->lines->count())
                            <div class="section-header">Ingredients</div>
                            <table class="items">
                                <thead>
                                    <tr>
                                        <th style="width: 16px;">#</th>
                                        <th>Ingredient</th>
                                        <th class="right" style="width: 42px;">Qty</th>
                                        <th style="width: 34px;">UOM</th>
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
                    <td style="width: 60%; vertical-align: top; padding-left: 10px; border-left: 1px solid #e5e7eb;">
                        @if ($recipe->steps->count() && ! $hasStepImgs)
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

            {{-- Steps grid (when images) --}}
            @if ($recipe->steps->count() && $hasStepImgs)
                <div style="margin-top: 8px;">
                    <div class="section-header">Preparation Steps</div>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 4px;">
                        @foreach ($recipe->steps->chunk(3) as $row)
                            <tr>
                                @foreach ($row as $step)
                                    <td style="width: 33.33%; vertical-align: top; padding: 0;">
                                        <div class="step-card">
                                            @if (isset($stepImgs[$step->id]))
                                                <img src="{{ $stepImgs[$step->id] }}" style="width: 100%; height: 95px; object-fit: cover; display: block;" />
                                            @endif
                                            <div style="padding: 5px 7px;">
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

            {{-- Plating --}}
            @php $imgs = $recipeImages[$recipe->id] ?? ['dine_in' => [], 'takeaway' => []]; @endphp
            @if (count($imgs['dine_in']) || count($imgs['takeaway']))
                <div style="margin-top: 10px;">
                    <div class="section-header">Plating Presentation</div>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 5px;">
                        <tr>
                            @if (count($imgs['dine_in']))
                                <td style="width: {{ count($imgs['takeaway']) ? '50%' : '100%' }}; vertical-align: top;">
                                    <div class="plating-label">Dine-In</div>
                                    @foreach ($imgs['dine_in'] as $b64)
                                        <div style="margin-bottom: 3px;">
                                            <img src="{{ $b64 }}" class="plating-img" />
                                        </div>
                                    @endforeach
                                </td>
                            @endif
                            @if (count($imgs['takeaway']))
                                <td style="width: {{ count($imgs['dine_in']) ? '50%' : '100%' }}; vertical-align: top;">
                                    <div class="plating-label">Takeaway</div>
                                    @foreach ($imgs['takeaway'] as $b64)
                                        <div style="margin-bottom: 3px;">
                                            <img src="{{ $b64 }}" class="plating-img" />
                                        </div>
                                    @endforeach
                                </td>
                            @endif
                        </tr>
                    </table>
                </div>
            @endif

        @endforeach
    @endforeach

</div>

<div class="footer">
    <span class="footer-left">&copy; {{ now()->format('Y') }} {{ $brandName }} &middot; Confidential &amp; proprietary</span>
    <span class="footer-right">Generated {{ now()->format('d M Y') }} &middot; {{ $exportedBy }} &middot; Powered by Servora</span>
</div>
</body>
</html>
