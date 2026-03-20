<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ($company->brand_name ?? $company->name) }} - Training SOPs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #000; line-height: 1.5; }
        .container { padding: 30px; }
        .page-break { page-break-before: always; }
        .company-logo { max-height: 45px; max-width: 160px; margin-bottom: 6px; }
        .company-name { font-size: 16px; font-weight: bold; color: #000; margin-bottom: 2px; }
        .company-detail { font-size: 9px; color: #444; line-height: 1.6; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.items thead th { background: #000; color: #fff; padding: 5px 8px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items thead th.right { text-align: right; }
        table.items tbody td { padding: 5px 8px; border-bottom: 1px solid #ddd; font-size: 10px; color: #000; }
        table.items tbody td.right { text-align: right; }
        table.items tbody tr:nth-child(even) { background: #f5f5f5; }
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 8px; color: #999; text-align: center; }
        .toc-item { padding: 4px 0; border-bottom: 1px dotted #ccc; font-size: 11px; }
        .toc-category { font-size: 13px; font-weight: bold; margin-top: 12px; margin-bottom: 4px; padding-bottom: 3px; border-bottom: 2px solid #000; }
        .sop-header { display: table; width: 100%; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .sop-header-left { display: table-cell; vertical-align: top; width: 60%; }
        .sop-header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .recipe-title { font-size: 16px; font-weight: bold; }
        .printer-info { margin-top: 15px; padding: 6px; border: 1px solid #ddd; background: #f9f9f9; font-size: 8px; color: #666; }
    </style>
</head>
<body>
<div class="container">

    {{-- ═══ COVER / TOC PAGE ═══ --}}
    <div style="text-align: center; padding-top: 60px; padding-bottom: 40px;">
        {{-- Rubber Stamp --}}
        <div style="margin-bottom: 20px;">
            <div style="display: inline-block; border: 3px solid #c00; color: #c00; padding: 5px 22px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; opacity: 0.7;">
                Private &amp; Confidential
            </div>
        </div>

        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" style="max-height: 60px; max-width: 200px; margin-bottom: 15px;" />
        @endif
        <div style="font-size: 28px; font-weight: bold; color: #000; margin-bottom: 5px;">{{ $brandName }}</div>
        @if ($company->brand_name && $company->name !== $company->brand_name)
            <div style="font-size: 12px; color: #666; margin-bottom: 3px;">{{ $company->name }}</div>
        @endif
        @if ($company->registration_number)
            <div style="font-size: 10px; color: #999;">Reg: {{ $company->registration_number }}</div>
        @endif
        <div style="font-size: 18px; font-weight: bold; color: #333; margin-top: 30px; letter-spacing: 2px; text-transform: uppercase;">
            Standard Operating Procedures
        </div>
        <div style="font-size: 11px; color: #666; margin-top: 8px;">Training Manual</div>
        <div style="font-size: 10px; color: #999; margin-top: 30px;">
            Generated: {{ now()->format('d M Y, h:i A') }}<br>
            Exported by: {{ $exportedBy }}
        </div>
        <div style="margin-top: 40px; font-size: 9px; color: #999; font-style: italic;">
            This manual is confidential &amp; property of {{ $brandName }}. Unauthorised reproduction or distribution is strictly prohibited.
        </div>
    </div>

    {{-- Table of Contents --}}
    <div style="margin-top: 30px;">
        <div style="font-size: 16px; font-weight: bold; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 5px;">
            Table of Contents
        </div>

        @php $sopNumber = 0; @endphp
        @foreach ($grouped as $categoryName => $catRecipes)
            <div class="toc-category">{{ $categoryName }}</div>
            @foreach ($catRecipes as $recipe)
                @php $sopNumber++; @endphp
                <div class="toc-item">
                    <span style="color: #666; font-size: 9px; margin-right: 5px;">{{ $sopNumber }}.</span>
                    {{ $recipe->name }}
                    @if ($recipe->code)
                        <span style="color: #999; font-size: 9px;">({{ $recipe->code }})</span>
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

            {{-- Rubber Stamp --}}
            <div style="text-align: right; margin-bottom: 8px;">
                <div style="display: inline-block; border: 3px solid #c00; color: #c00; padding: 4px 18px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; opacity: 0.7;">
                    Private &amp; Confidential
                </div>
            </div>

            {{-- SOP Header --}}
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
                    <div style="font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #666;">Standard Operating Procedure</div>
                    @if ($recipe->code)
                        <div style="font-size: 11px; font-family: monospace; color: #333; margin-top: 2px;">{{ $recipe->code }}</div>
                    @endif
                    <div style="font-size: 9px; color: #999; margin-top: 3px;">SOP #{{ $sopNumber }} | {{ $categoryName }}</div>
                </div>
            </div>

            {{-- Recipe Info --}}
            <div style="margin-bottom: 12px;">
                <div class="recipe-title">{{ $recipe->name }}</div>
                @if ($recipe->description)
                    <div style="font-size: 10px; color: #444; margin-top: 3px;">{{ $recipe->description }}</div>
                @endif
                <div style="font-size: 9px; color: #666; margin-top: 3px;">
                    Yield: {{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}
                    @if ($recipe->video_url) | Video: {{ $recipe->video_url }} @endif
                </div>
            </div>

            {{-- Ingredients --}}
            @if ($recipe->lines->count())
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; color: #333;">Ingredients</div>
                    <table class="items">
                        <thead>
                            <tr>
                                <th style="width: 25px;">#</th>
                                <th>Ingredient</th>
                                <th class="right" style="width: 70px;">Qty</th>
                                <th style="width: 50px;">UOM</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recipe->lines as $idx => $line)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>{{ $line->ingredient?->name ?? '—' }}</td>
                                    <td class="right">{{ rtrim(rtrim(number_format($line->quantity, 4), '0'), '.') }}</td>
                                    <td>{{ $line->uom?->abbreviation ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Cooking Steps --}}
            @if ($recipe->steps->count())
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; color: #333;">Cooking Steps</div>
                    @foreach ($recipe->steps as $step)
                        <div style="margin-bottom: 6px; padding-left: 3px;">
                            <div style="font-size: 10px; font-weight: bold; color: #333;">
                                Step {{ $step->sort_order + 1 }}{{ $step->title ? ': ' . $step->title : '' }}
                            </div>
                            <div style="font-size: 9px; color: #000; line-height: 1.5; padding-left: 8px;">
                                {!! nl2br(e($step->instruction)) !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Plating Images --}}
            @php $imgs = $recipeImages[$recipe->id] ?? ['dine_in' => [], 'takeaway' => []]; @endphp
            @if (count($imgs['dine_in']))
                <div style="margin-bottom: 10px;">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; color: #333;">Plating — Dine-In</div>
                    @foreach ($imgs['dine_in'] as $b64)
                        <div style="margin-bottom: 5px; text-align: center;">
                            <img src="{{ $b64 }}" style="max-width: 350px; max-height: 200px; border: 1px solid #ddd;" />
                        </div>
                    @endforeach
                </div>
            @endif
            @if (count($imgs['takeaway']))
                <div style="margin-bottom: 10px;">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; color: #333;">Plating — Takeaway</div>
                    @foreach ($imgs['takeaway'] as $b64)
                        <div style="margin-bottom: 5px; text-align: center;">
                            <img src="{{ $b64 }}" style="max-width: 350px; max-height: 200px; border: 1px solid #ddd;" />
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Printer Info --}}
            <div class="printer-info">
                <strong>{{ $brandName }}</strong>
                @if ($company->registration_number) | Reg: {{ $company->registration_number }} @endif
                @if ($company->phone) | Tel: {{ $company->phone }} @endif
                | Exported by: {{ $exportedBy }}
                | Printed: {{ now()->format('d M Y, h:i A') }}
                | SOP #{{ $sopNumber }}: {{ $recipe->name }}
                @if ($recipe->code) ({{ $recipe->code }}) @endif
            </div>

            {{-- Confidential Footer --}}
            <div style="margin-top: 8px; text-align: center; font-size: 8px; color: #999; font-style: italic;">
                This manual is confidential &amp; property of {{ $brandName }}. Unauthorised reproduction or distribution is strictly prohibited.
            </div>
        @endforeach
    @endforeach

    <div class="footer">
        Generated on {{ now()->format('d M Y, h:i A') }} by {{ $exportedBy }} | {{ $brandName }}
        <br>This manual is confidential &amp; property of {{ $brandName }}.
        <br>Powered by Servora - https://servora.com.my/
    </div>
</div>
</body>
</html>
