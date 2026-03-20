@extends('pdf.layout')

@section('title', 'SOP - ' . $recipe->name)

@section('content')
    {{-- Header with stamp --}}
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
            <div style="display: inline-block; border: 2px solid #c00; color: #c00; padding: 2px 10px; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; opacity: 0.75; margin-bottom: 4px;">Private &amp; Confidential</div>
            <div class="doc-title">Standard Operating Procedure</div>
            @if ($recipe->code)
                <div class="doc-number">{{ $recipe->code }}</div>
            @endif
        </div>
    </div>

    {{-- Recipe Title + QR --}}
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="vertical-align: top;">
                <div style="font-size: 14px; font-weight: bold; color: #000;">{{ $recipe->name }}</div>
                <div style="font-size: 8px; color: #555; margin-top: 2px;">
                    @if ($recipe->category)<span>{{ $recipe->category }}</span> · @endif
                    Yield: {{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}
                </div>
                @if ($recipe->description)
                    <div style="font-size: 8px; color: #444; margin-top: 2px;">{{ $recipe->description }}</div>
                @endif
            </td>
            @if ($videoQr)
                <td style="width: 80px; vertical-align: top; text-align: center; padding-left: 8px;">
                    <img src="{{ $videoQr }}" style="width: 65px; height: 65px;" />
                    <div style="font-size: 6px; color: #666; margin-top: 1px; font-weight: bold;">Scan for Video</div>
                </td>
            @endif
        </tr>
    </table>

    {{-- Ingredients & Cooking Steps — Side by Side --}}
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            {{-- Ingredients (left) --}}
            <td style="width: 40%; vertical-align: top; padding-right: 8px;">
                @if ($recipe->lines->count())
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; border-bottom: 1px solid #ccc; padding-bottom: 2px;">Ingredients</div>
                    <table class="items" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 16px;">#</th>
                                <th>Ingredient</th>
                                <th class="right" style="width: 45px;">Qty</th>
                                <th style="width: 35px;">UOM</th>
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
                @endif
            </td>

            {{-- Cooking Steps (right) --}}
            <td style="width: 60%; vertical-align: top; padding-left: 8px; border-left: 1px solid #ddd;">
                @if ($recipe->steps->count())
                    <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; border-bottom: 2px solid #000; padding-bottom: 2px;">Cooking Steps</div>
                    @foreach ($recipe->steps as $step)
                        <div style="margin-bottom: 4px;">
                            <div style="font-size: 10px; font-weight: bold; color: #000;">
                                {{ $step->sort_order + 1 }}.{{ $step->title ? ' ' . $step->title : '' }}
                            </div>
                            <div style="font-size: 9px; color: #000; line-height: 1.4; padding-left: 12px;">
                                {!! nl2br(e($step->instruction)) !!}
                            </div>
                        </div>
                    @endforeach
                @endif
            </td>
        </tr>
    </table>

    {{-- Plating Images — Side by Side --}}
    @if (count($dineInBase64) || count($takeawayBase64))
        <div style="margin-bottom: 8px;">
            <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; border-bottom: 1px solid #ccc; padding-bottom: 2px;">Plating</div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    @if (count($dineInBase64))
                        <td style="width: {{ count($takeawayBase64) ? '50%' : '100%' }}; vertical-align: top; padding-right: {{ count($takeawayBase64) ? '5px' : '0' }};">
                            <div style="font-size: 7px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px;">Dine-In</div>
                            @foreach ($dineInBase64 as $b64)
                                <div style="margin-bottom: 3px;">
                                    <img src="{{ $b64 }}" style="width: 100%; max-height: 140px; border: 1px solid #ddd;" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                    @if (count($takeawayBase64))
                        <td style="width: {{ count($dineInBase64) ? '50%' : '100%' }}; vertical-align: top; padding-left: {{ count($dineInBase64) ? '5px' : '0' }};">
                            <div style="font-size: 7px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px;">Takeaway</div>
                            @foreach ($takeawayBase64 as $b64)
                                <div style="margin-bottom: 3px;">
                                    <img src="{{ $b64 }}" style="width: 100%; max-height: 140px; border: 1px solid #ddd;" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

@endsection
