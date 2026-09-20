@extends('pdf.layout')

@section('title', 'Asset Count Sheet')

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
            @if ($company?->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Asset Count Sheet</div>
            <div class="doc-number">{{ $count->reference_number ?: 'AC-' . $count->id }}</div>
            <div class="doc-status">{{ ucfirst($count->status) }}</div>
        </div>
    </div>

    {{-- Meta --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Count Date</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">{{ $count->count_date?->format('d M Y') ?? '—' }}</td>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Outlet</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-bottom: 1px solid #e5e7eb;">{{ $count->outlet?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Area</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb;">{{ $count->department?->name ?? 'Whole outlet' }}</td>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Prepared By</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a;">{{ $count->createdBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 26px;">#</th>
                <th style="width: 46px;" class="center">Photo</th>
                <th>Asset</th>
                <th class="center" style="width: 46px;">UOM</th>
                <th class="center" style="width: 96px;">Counted</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 0; @endphp

            @foreach ($groupedLines as $groupName => $lines)
                {{-- Category header --}}
                <tr>
                    <td colspan="5" style="background: #e5e7eb; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 8px; border-bottom: 2px solid #9ca3af;">
                        {{ $groupName }} ({{ $lines->count() }})
                    </td>
                </tr>

                @foreach ($lines as $line)
                    @php
                        $rowNum++;
                        $asset    = $line->asset;
                        $category = $asset?->category;
                        // Only when a parent swallowed it in the grouping above,
                        // so the row says which shelf inside "Equipment" it is.
                        $subName  = $category?->parent ? $category->name : '';
                        $thumb    = $thumbs[$asset?->id] ?? null;
                    @endphp

                    <tr>
                        <td>{{ $rowNum }}</td>

                        {{-- About 9mm on the page: enough to tell two mixing bowls
                             apart at arm's length, small enough that the row stays one
                             line and the sheet stays short. The cell keeps its height
                             whether or not there is a photo, so the rows stay even.

                             The width and height come from the controller, already
                             fitted to the box — dompdf has no object-fit, so an img
                             told to be square would squash a 4:3 photo to prove it. --}}
                        <td class="center" style="height: 34px;">
                            @if ($thumb)
                                <img src="{{ $thumb['src'] }}" alt=""
                                     style="width: {{ $thumb['w'] }}px; height: {{ $thumb['h'] }}px;" />
                            @endif
                        </td>

                        <td>
                            @if ($subName)
                                <span style="color: #888; font-size: 9px;">{{ $subName }} &middot; </span>
                            @endif
                            {{ $asset?->name ?? '(Deleted asset)' }}
                            @if ($asset?->code)
                                <span style="color: #888; font-size: 9px;"> ({{ $asset->code }})</span>
                            @endif
                            @if ($asset?->brand || $asset?->model)
                                <div style="color: #888; font-size: 8.5px;">{{ trim($asset->brand . ' ' . $asset->model) }}</div>
                            @endif
                        </td>

                        <td class="center">{{ $asset?->uom?->abbreviation ?? '' }}</td>

                        {{-- Deliberately blank. The sheet does not print what the
                             register expects: a number on the paper is a number that
                             gets copied back onto it. --}}
                        <td style="border: 1px solid #000;">&nbsp;</td>
                    </tr>
                @endforeach
            @endforeach

            @if ($rowNum === 0)
                <tr>
                    <td colspan="5" style="padding: 18px; text-align: center; color: #64748b;">
                        No assets on this count yet.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Notes --}}
    @if ($count->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $count->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 18px 0; margin-top: 40px; page-break-inside: avoid;">
        <tr>
            @foreach (['Counted By', 'Verified By', 'Audited By'] as $role)
                <td style="width: 33.33%; vertical-align: top;">
                    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #475569; margin-bottom: 6px;">{{ $role }}</div>

                    <div style="height: 70px; border: 1px solid #cbd5e1; background: #fafafa;"></div>
                    <div style="font-size: 8pt; color: #94a3b8; text-align: center; margin-top: 2px; letter-spacing: 0.5px;">Signature</div>

                    <div style="margin-top: 16px; border-bottom: 1px solid #555; height: 14px;"></div>
                    <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Name</div>

                    <div style="margin-top: 14px; border-bottom: 1px solid #555; height: 14px;"></div>
                    <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Date</div>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
