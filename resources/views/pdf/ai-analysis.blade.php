@extends('pdf.layout')

@section('title', 'AI Analysis Report — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            <div class="company-detail">Outlet: {{ $outletName ?? 'All Outlets' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">AI Analysis Report</div>
            <div class="doc-number">{{ $periodLabel }}</div>
            <div style="font-size: 9px; color: #666; margin-top: 4px;">
                {{ ucwords(str_replace('_', ' ', $analysisType)) }}
                &middot; Powered by Servora AI
            </div>
        </div>
    </div>

    <style>
        .ai-content { font-size: 10px; line-height: 1.7; color: #111; }
        .ai-content h2 { font-size: 13px; font-weight: bold; margin: 16px 0 6px 0; padding-bottom: 3px; border-bottom: 1px solid #ccc; }
        .ai-content h3 { font-size: 11px; font-weight: bold; margin: 12px 0 4px 0; }
        .ai-content p { margin: 4px 0 8px 0; }
        .ai-content ul, .ai-content ol { margin: 4px 0 8px 16px; }
        .ai-content li { margin-bottom: 3px; }
        .ai-content strong { font-weight: bold; }
        .ai-content table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9px; }
        .ai-content table th { background: #333; color: #fff; padding: 4px 6px; text-align: left; font-size: 8px; text-transform: uppercase; }
        .ai-content table td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        .ai-content table tr:nth-child(even) { background: #f9f9f9; }
        .ai-content blockquote { border-left: 3px solid #999; padding-left: 10px; color: #555; margin: 8px 0; }
        .ai-content code { font-family: monospace; background: #f0f0f0; padding: 1px 3px; font-size: 9px; }
    </style>

    <div class="ai-content">
        {!! $htmlContent !!}
    </div>
@endsection
