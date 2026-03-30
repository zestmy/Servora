<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Credit & Debit Notes</h2>
        </div>
        <a href="{{ route('purchasing.credit-notes.create') }}"
           class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search note number..."
                       class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Types</option>
                <option value="debit_note">Debit Note</option>
                <option value="credit_note">Credit Note</option>
            </select>
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="issued">Issued</option>
                <option value="applied">Applied</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select wire:model.live="supplierFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">All Suppliers</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm" />
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Number</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Direction</th>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-center">Date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($creditNotes as $cn)
                    @php
                        $statusBadge = match($cn->status) {
                            'draft'     => 'bg-gray-100 text-gray-600',
                            'issued'    => 'bg-blue-100 text-blue-700',
                            'applied'   => 'bg-green-100 text-green-700',
                            'acknowledged' => 'bg-teal-100 text-teal-700',
                            'cancelled' => 'bg-red-100 text-red-600',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                        $typeBadge = $cn->type === 'debit_note'
                            ? 'bg-orange-50 text-orange-600'
                            : 'bg-purple-50 text-purple-600';
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-indigo-600">
                            <a href="{{ route('purchasing.credit-notes.edit', $cn->id) }}" class="hover:underline">
                                {{ $cn->credit_note_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs {{ $typeBadge }}">
                                {{ $cn->type === 'debit_note' ? 'Debit' : 'Credit' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ ucfirst($cn->direction ?? '—') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-700">{{ $cn->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $cn->outlet?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $cn->issued_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ number_format($cn->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                {{ ucfirst($cn->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                {{-- Action menu (PDF, Print, Duplicate, Share) --}}
                                <x-doc-action-menu
                                    :pdfUrl="route('purchasing.pdf', ['type' => 'cn', 'id' => $cn->id])"
                                    :duplicateUrl="route('purchasing.credit-notes.create', ['duplicate' => $cn->id])"
                                    :docNumber="$cn->credit_note_number"
                                    :docType="$cn->type === 'debit_note' ? 'Debit Note' : 'Credit Note'"
                                />

                                {{-- Edit / View --}}
                                <a href="{{ route('purchasing.credit-notes.edit', $cn->id) }}"
                                   title="{{ $cn->status === 'draft' ? 'Edit' : 'View' }}"
                                   class="text-gray-400 hover:text-indigo-600 transition p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>

                                {{-- Issue --}}
                                @if ($cn->status === 'draft')
                                    <button wire:click="issue({{ $cn->id }})" wire:confirm="Issue this note?"
                                            title="Issue" class="text-blue-400 hover:text-blue-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                @endif

                                {{-- Apply --}}
                                @if ($cn->status === 'issued')
                                    <button wire:click="apply({{ $cn->id }})" wire:confirm="Apply this note to the linked invoice?"
                                            title="Apply" class="text-green-500 hover:text-green-700 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                @endif

                                {{-- Cancel --}}
                                @if (in_array($cn->status, ['draft', 'issued']))
                                    <button wire:click="cancel({{ $cn->id }})" wire:confirm="Cancel this note?"
                                            title="Cancel" class="text-red-400 hover:text-red-600 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No credit/debit notes found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($creditNotes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $creditNotes->links() }}</div>
        @endif
    </div>
</div>
