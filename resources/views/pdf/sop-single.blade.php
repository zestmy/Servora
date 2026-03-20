@extends('pdf.layout')

@section('title', 'SOP - ' . $recipe->name)

@section('content')
    {{-- Rubber Stamp --}}
    <div style="text-align: right; margin-bottom: 8px;">
        <div style="display: inline-block; border: 3px solid #c00; color: #c00; padding: 4px 18px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; opacity: 0.7;">
            Private &amp; Confidential
        </div>
    </div>

    {{-- Header --}}
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
            @if ($company->phone)
                <div class="company-detail">Tel: {{ $company->phone }}</div>
            @endif
            @if ($company->address)
                <div class="company-detail">{{ $company->address }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Standard Operating Procedure</div>
            @if ($recipe->code)
                <div class="doc-number">{{ $recipe->code }}</div>
            @endif
            <div class="doc-status">SOP</div>
        </div>
    </div>

    {{-- Recipe Info --}}
    <div style="margin-bottom: 15px;">
        <table class="meta-table">
            <tr><td class="label">Recipe Name</td><td class="value" style="font-size: 14px; font-weight: bold;">{{ $recipe->name }}</td></tr>
            @if ($recipe->category)
                <tr><td class="label">Category</td><td class="value">{{ $recipe->category }}</td></tr>
            @endif
            @if ($recipe->description)
                <tr><td class="label">Description</td><td class="value">{{ $recipe->description }}</td></tr>
            @endif
            <tr>
                <td class="label">Yield</td>
                <td class="value">{{ rtrim(rtrim(number_format($recipe->yield_quantity, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</td>
            </tr>
        </table>
    </div>

    {{-- Ingredients --}}
    @if ($recipe->lines->count())
        <div style="margin-bottom: 18px;">
            <h3 style="font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px;">Ingredients</h3>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>Ingredient</th>
                        <th class="right" style="width: 80px;">Quantity</th>
                        <th style="width: 60px;">UOM</th>
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
        <div style="margin-bottom: 18px;">
            <h3 style="font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px;">Cooking Steps</h3>
            @foreach ($recipe->steps as $step)
                <div style="margin-bottom: 10px; padding-left: 5px;">
                    <div style="font-size: 11px; font-weight: bold; color: #333; margin-bottom: 2px;">
                        Step {{ $step->sort_order + 1 }}{{ $step->title ? ': ' . $step->title : '' }}
                    </div>
                    <div style="font-size: 10px; color: #000; line-height: 1.6; padding-left: 10px;">
                        {!! nl2br(e($step->instruction)) !!}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Plating Images — Side by Side --}}
    @if (count($dineInBase64) || count($takeawayBase64))
        <div style="margin-bottom: 18px;">
            <h3 style="font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px;">Plating Presentation</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    @if (count($dineInBase64))
                        <td style="width: {{ count($takeawayBase64) ? '50%' : '100%' }}; vertical-align: top; padding-right: {{ count($takeawayBase64) ? '8px' : '0' }};">
                            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 4px;">Dine-In</div>
                            @foreach ($dineInBase64 as $b64)
                                <div style="margin-bottom: 6px;">
                                    <img src="{{ $b64 }}" style="width: 100%; max-height: 200px; border: 1px solid #ddd; object-fit: cover;" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                    @if (count($takeawayBase64))
                        <td style="width: {{ count($dineInBase64) ? '50%' : '100%' }}; vertical-align: top; padding-left: {{ count($dineInBase64) ? '8px' : '0' }};">
                            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 4px;">Takeaway</div>
                            @foreach ($takeawayBase64 as $b64)
                                <div style="margin-bottom: 6px;">
                                    <img src="{{ $b64 }}" style="width: 100%; max-height: 200px; border: 1px solid #ddd; object-fit: cover;" />
                                </div>
                            @endforeach
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- Training Video QR --}}
    @if ($videoQr)
        <div style="margin-bottom: 18px;">
            <h3 style="font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px;">Training Video</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 90px; vertical-align: top;">
                        <img src="{{ $videoQr }}" style="width: 80px; height: 80px;" />
                    </td>
                    <td style="vertical-align: middle; padding-left: 10px;">
                        <div style="font-size: 10px; font-weight: bold; color: #333; margin-bottom: 3px;">Scan QR to watch training video</div>
                        <div style="font-size: 8px; color: #666; word-break: break-all;">{{ $recipe->video_url }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Printer Info --}}
    <div style="margin-top: 20px; padding: 8px; border: 1px solid #ddd; background: #f9f9f9; font-size: 8px; color: #666;">
        <strong>Document Details:</strong>
        {{ $brandName }}
        @if ($company->registration_number) | Reg: {{ $company->registration_number }} @endif
        @if ($company->phone) | Tel: {{ $company->phone }} @endif
        | Exported by: {{ $exportedBy }}
        | Printed: {{ now()->format('d M Y, h:i A') }}
        | SOP: {{ $recipe->name }}
        @if ($recipe->code) ({{ $recipe->code }}) @endif
    </div>

    {{-- Confidential Footer --}}
    <div style="margin-top: 12px; text-align: center; font-size: 8px; color: #999; font-style: italic;">
        This manual is confidential &amp; property of {{ $brandName }}. Unauthorised reproduction or distribution is strictly prohibited.
    </div>
@endsection
