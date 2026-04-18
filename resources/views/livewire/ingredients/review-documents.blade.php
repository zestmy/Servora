<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Inventory &amp; Recipes / Review Documents</p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Review Documents</h2>
            <p class="text-xs text-gray-500 mt-0.5">Scanned supplier documents waiting for review. Open one to match the line items against your ingredient list and import.</p>
        </div>
        <a href="{{ route('ingredients.scan-document') }}"
           class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Scan Document
        </a>
    </div>

    {{-- Status tabs --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
        @foreach ([
            'extracted' => ['Pending Review', $counts['extracted'], 'text-indigo-700 border-indigo-300 bg-indigo-50'],
            'failed'    => ['Failed',         $counts['failed'],    'text-red-700 border-red-200 bg-red-50'],
            'imported'  => ['Imported',       $counts['imported'],  'text-green-700 border-green-200 bg-green-50'],
            'all'       => ['All',            null,                 'text-gray-700 border-gray-200 bg-gray-50'],
        ] as $k => $v)
            @php [$label, $count, $tone] = $v; @endphp
            <button wire:click="$set('statusFilter', '{{ $k }}')"
                    class="px-3 py-1.5 rounded-lg border font-medium transition
                           {{ $statusFilter === $k ? $tone : 'text-gray-500 border-gray-200 hover:bg-gray-50' }}">
                {{ $label }}@if ($count !== null && $count > 0) · {{ $count }}@endif
            </button>
        @endforeach
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="divide-y divide-gray-100">
            @forelse ($docs as $doc)
                <div class="flex items-start gap-4 p-4">
                    {{-- Status dot --}}
                    <div class="flex-shrink-0 mt-1 w-2.5 h-2.5 rounded-full
                                {{ $doc->status === 'extracted' ? 'bg-indigo-500' : ($doc->status === 'failed' ? 'bg-red-500' : ($doc->status === 'imported' ? 'bg-green-500' : 'bg-gray-300')) }}"></div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-gray-800 truncate">{{ $doc->original_filename }}</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium
                                         {{ $doc->status === 'extracted' ? 'bg-indigo-100 text-indigo-700' : ($doc->status === 'failed' ? 'bg-red-100 text-red-700' : ($doc->status === 'imported' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600')) }}">
                                {{ ucfirst($doc->status) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            @if ($doc->supplier_name_detected)
                                <span>Supplier: <strong class="text-gray-700">{{ $doc->supplier_name_detected }}</strong>
                                    @if ($doc->supplier_id)
                                        <span class="text-green-600">· linked</span>
                                    @else
                                        <span class="text-amber-600">· not in DB</span>
                                    @endif
                                </span>
                            @endif
                            @if ($doc->document_date_detected)
                                <span>Doc date: {{ $doc->document_date_detected->format('d M Y') }}</span>
                            @endif
                            @if ($doc->status === 'extracted')
                                <span>{{ count($doc->extracted_items ?? []) }} items</span>
                            @endif
                            <span class="text-gray-400">
                                Scanned {{ $doc->created_at->diffForHumans() }} by {{ $doc->uploader?->name ?? '—' }}
                            </span>
                        </div>
                        @if ($doc->status === 'failed' && $doc->error_message)
                            <p class="text-xs text-red-600 mt-1 italic">{{ $doc->error_message }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if ($doc->status === 'extracted')
                            <a href="{{ route('ingredients.review-documents.show', $doc->id) }}"
                               class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                Review
                            </a>
                            <button wire:click="discard({{ $doc->id }})" wire:confirm="Discard this scanned document?"
                                    class="text-gray-400 hover:text-red-600 text-xs">Discard</button>
                        @elseif ($doc->status === 'failed')
                            <a href="{{ route('ingredients.scan-document') }}"
                               class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                Rescan
                            </a>
                            <button wire:click="discard({{ $doc->id }})" wire:confirm="Discard this scanned document?"
                                    class="text-gray-400 hover:text-red-600 text-xs">Discard</button>
                        @elseif ($doc->status === 'imported')
                            <span class="text-xs text-green-600">Imported {{ $doc->imported_at?->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-400 text-sm">No documents in this list.</div>
            @endforelse
        </div>

        @if ($docs->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $docs->links() }}</div>
        @endif
    </div>
</div>
