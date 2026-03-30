@props([
    'pdfUrl'       => null,
    'duplicateUrl' => null,
    'docNumber'    => '',
    'docType'      => 'Document',
])

@php
    $shareText = urlencode("{$docType} {$docNumber}");
    $pdfLink   = $pdfUrl ? url($pdfUrl) : '';
    $shareBody = urlencode("{$docType} {$docNumber} — " . ($pdfLink ?: request()->url()));
@endphp

<div x-data="{ open: false, share: false }" class="relative inline-block" @click.outside="open = false; share = false">
    {{-- Kebab trigger --}}
    <button @click="open = !open; share = false" type="button" title="More actions"
            class="text-gray-400 hover:text-gray-600 transition p-1 rounded hover:bg-gray-100">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-8 z-30 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 text-xs">

        {{-- PDF --}}
        @if ($pdfUrl)
            <a href="{{ $pdfUrl }}" target="_blank"
               class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download PDF
            </a>
        @endif

        {{-- Print --}}
        @if ($pdfUrl)
            <a href="{{ $pdfUrl }}" target="_blank" onclick="event.preventDefault(); let w = window.open(this.href); w.onload = () => { w.print(); };"
               class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </a>
        @endif

        {{-- Duplicate --}}
        @if ($duplicateUrl)
            <a href="{{ $duplicateUrl }}"
               class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Duplicate
            </a>
        @endif

        {{-- Divider --}}
        <div class="border-t border-gray-100 my-1"></div>

        {{-- Share submenu --}}
        <div class="relative" @mouseenter="share = true" @mouseleave="share = false">
            <button type="button" class="w-full flex items-center justify-between gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                    Share
                </span>
                <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Share sub-dropdown --}}
            <div x-show="share" x-cloak
                 class="absolute right-full top-0 mr-1 w-44 bg-white rounded-lg shadow-lg border border-gray-200 py-1">

                {{-- Email --}}
                <a href="mailto:?subject={{ $shareText }}&body={{ $shareBody }}"
                   class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email
                </a>

                {{-- WhatsApp --}}
                <a href="https://wa.me/?text={{ $shareBody }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4 text-green-500" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    WhatsApp
                </a>

                {{-- Copy Link --}}
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $pdfLink ?: request()->url() }}'); open = false; share = false; $dispatch('notify', { message: 'Link copied!' })"
                        class="w-full flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Copy Link
                </button>
            </div>
        </div>
    </div>
</div>
