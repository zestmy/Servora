<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">Sales / POS Sync</p>
            <h1 class="page-title mt-1">POS Sync</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.downloads') }}" class="btn-secondary">
                <span class="sm:hidden">Downloads</span>
                <span class="hidden sm:inline">Download Agent v{{ \App\Models\PosAgent::CURRENT_VERSION }}</span>
            </a>
            <button wire:click="openCreate" class="btn-primary">
                <span class="sm:hidden">+ Add</span>
                <span class="hidden sm:inline">+ Add Agent</span>
            </button>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            A POS sync agent is a small program on the POS terminal that sends the day's Zeoniq sales
            report to Servora automatically — the same numbers as the manual Excel upload, without the
            upload. Install it from the downloads page, add an agent here, then enter its pairing code
            on the terminal.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Server address for the agent: <code class="px-1 py-0.5 bg-gray-100 rounded">{{ $this->serverUrl() }}</code>
        </p>
    </div>

    {{-- Tabs --}}
    <div class="seg mb-4">
        <button wire:click="setTab('agents')" class="seg-item {{ $tab === 'agents' ? 'seg-item-on' : '' }}">
            Agents
        </button>
        <button wire:click="setTab('batches')" class="seg-item {{ $tab === 'batches' ? 'seg-item-on' : '' }}">
            Uploads
            @if ($needsAttention > 0)
                <span class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-warning-50 text-warning-800 text-xs font-semibold">
                    {{ $needsAttention }}
                </span>
            @endif
        </button>
    </div>

    @if ($tab === 'agents')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[720px]">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Agent</th>
                            <th class="px-4 py-3 text-left">Outlet</th>
                            <th class="px-4 py-3 text-left">Machine</th>
                            <th class="px-4 py-3 text-left w-44">Status</th>
                            <th class="px-4 py-3 text-left w-44">Last upload</th>
                            <th class="px-4 py-3 text-center w-40">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($agents as $agent)
                            @php $lastBatch = $agent->batches()->latest('id')->first(); @endphp
                            <tr class="hover:bg-gray-50 {{ $agent->isRevoked() ? 'opacity-60' : '' }}" wire:key="agent-{{ $agent->id }}">
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $agent->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $agent->outlet?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $agent->hostname ?? '—' }}
                                    @if ($agent->agent_version)
                                        <span class="block text-xs text-gray-500">
                                            v{{ $agent->agent_version }}@if ($agent->os) · {{ $agent->os }}@endif
                                            @if ($agent->isOutdated())
                                                <span class="ml-1 px-1 py-0.5 rounded bg-warning-50 text-warning-700 text-[10px]"
                                                      title="The current install zip ships v{{ \App\Models\PosAgent::CURRENT_VERSION }} — re-download and replace the exe on this terminal">
                                                    v{{ \App\Models\PosAgent::CURRENT_VERSION }} available
                                                </span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $agent->isOnline() ? 'badge-success' : ($agent->isRevoked() ? 'badge-neutral' : 'badge-warning') }}">
                                        {{ $agent->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    @if ($lastBatch)
                                        {{ $lastBatch->created_at->diffForHumans() }}
                                        <span class="block text-gray-500">{{ $lastBatch->statusLabel() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @unless ($agent->isRevoked())
                                        <button wire:click="showCode({{ $agent->id }})"
                                                class="text-brand-600 hover:text-brand-800 text-xs"
                                                title="Show a pairing code to enter on the POS terminal">
                                            {{ $agent->isPaired() ? 'Re-pair' : 'Pairing code' }}
                                        </button>
                                        <button wire:click="revoke({{ $agent->id }})"
                                                data-confirm-delete="Revoke this agent? Its token stops working immediately and the terminal will need a new pairing code. The row and its uploads stay for the audit trail."
                                                class="ml-2 text-danger-500 hover:text-danger-700 text-xs">Revoke</button>
                                    @else
                                        <span class="text-xs text-gray-400">Revoked {{ $agent->revoked_at?->diffForHumans() }}</span>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-600 text-sm">
                                    No agents yet. Add one for each POS terminal that should send its
                                    sales to Servora automatically.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[860px]">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">File</th>
                            <th class="px-4 py-3 text-left">Agent</th>
                            <th class="px-4 py-3 text-left w-40">Dates</th>
                            <th class="px-4 py-3 text-left w-36">Status</th>
                            <th class="px-4 py-3 text-left">Outcome</th>
                            <th class="px-4 py-3 text-center w-44">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr class="hover:bg-gray-50 align-top" wire:key="batch-{{ $batch->id }}">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-700">{{ $batch->source_filename }}</span>
                                    <span class="block text-xs text-gray-500">{{ $batch->created_at->format('j M Y, H:i') }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $batch->agent?->name ?? '—' }}
                                    <span class="block text-xs text-gray-500">{{ $batch->outlet?->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    @if ($batch->date_from)
                                        {{ $batch->date_from->format('j M') }}@if ($batch->date_to && ! $batch->date_to->isSameDay($batch->date_from)) – {{ $batch->date_to->format('j M') }}@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ match($batch->status) {
                                        \App\Models\PosSalesBatch::STATUS_APPLIED => 'badge-success',
                                        \App\Models\PosSalesBatch::STATUS_FAILED => 'badge-danger',
                                        \App\Models\PosSalesBatch::STATUS_NEEDS_MAPPING,
                                        \App\Models\PosSalesBatch::STATUS_NEEDS_REVIEW => 'badge-warning',
                                        default => 'badge-neutral',
                                    } }}">{{ $batch->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 max-w-md">
                                    @if ($batch->status === \App\Models\PosSalesBatch::STATUS_APPLIED && $batch->result)
                                        {{ $batch->result['created'] ?? 0 }} created,
                                        {{ $batch->result['replaced'] ?? 0 }} replaced
                                        @if (($batch->result['skipped'] ?? 0) > 0), {{ $batch->result['skipped'] }} skipped @endif
                                        @if ($batch->applied_by)
                                            <span class="block text-gray-500">applied by {{ $batch->appliedBy?->name }}</span>
                                        @endif
                                    @elseif ($batch->error)
                                        {{ $batch->error }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($batch->status === \App\Models\PosSalesBatch::STATUS_NEEDS_MAPPING)
                                        <button wire:click="openMapping({{ $batch->id }})"
                                                class="text-brand-600 hover:text-brand-800 text-xs">Map departments</button>
                                    @elseif ($batch->status === \App\Models\PosSalesBatch::STATUS_NEEDS_REVIEW)
                                        <button wire:click="applyAnyway({{ $batch->id }})"
                                                data-confirm-delete="Apply this batch over the existing records? The POS figures replace what is there now for the dates it covers."
                                                class="text-warning-700 hover:text-warning-800 text-xs">Apply anyway</button>
                                    @elseif ($batch->status === \App\Models\PosSalesBatch::STATUS_FAILED)
                                        <button wire:click="retry({{ $batch->id }})"
                                                class="text-brand-600 hover:text-brand-800 text-xs">Retry</button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-gray-600 text-sm">
                                    Nothing uploaded yet. Once an agent is paired, its uploads and what
                                    became of them appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches && $batches->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $batches->links() }}</div>
            @endif
        </div>
    @endif

    {{-- Create modal --}}
    <div x-data="{ open: @entangle('showModal') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak
                 @keydown.escape.window="open = false"
                 class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Add Agent</h3>
                            <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form wire:submit.prevent="save" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                                       placeholder="e.g. Front counter till" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Outlet <span class="text-danger-500">*</span></label>
                                <select wire:model="outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">— Select —</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-600">
                                    The outlet whose sales this terminal reports. Chosen here, by you,
                                    before the machine is trusted — the agent cannot pick its own.
                                </p>
                                <x-input-error :messages="$errors->get('outlet_id')" class="mt-1" />
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                                <button type="submit" class="btn-primary">Create &amp; get code</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Pairing code modal --}}
    @if ($pairingCode)
        <template x-teleport="body">
            <div @keydown.escape.window="$wire.closeCode()"
                 class="fixed inset-0 z-[110] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" wire:click="closeCode"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Pair the agent</h3>
                        </div>
                        <div class="p-5 space-y-3 text-sm text-gray-700">
                            <p>On the POS terminal, run <code class="px-1 bg-gray-100 rounded">servora-pos-agent.exe pair</code> and enter:</p>
                            <div class="p-3 bg-gray-50 rounded-lg space-y-2">
                                <div>
                                    <p class="text-xs text-gray-500">Server address</p>
                                    <code class="text-sm">{{ $this->serverUrl() }}</code>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Pairing code</p>
                                    <p class="text-2xl font-mono font-bold tracking-[0.3em] text-gray-900">{{ $pairingCode }}</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-600">
                                The code works once and expires in
                                {{ \App\Models\PosAgent::PAIRING_TTL_MINUTES }} minutes. If it runs out,
                                come back and press Re-pair for a fresh one — the old code stops working
                                the moment a new one is issued.
                            </p>
                        </div>
                        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                            <button type="button" wire:click="closeCode" class="btn-primary">Done</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif

    {{-- Department mapping modal --}}
    @if ($mappingBatch)
        <template x-teleport="body">
            <div @keydown.escape.window="$wire.closeMapping()"
                 class="fixed inset-0 z-[110] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" wire:click="closeMapping"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg" @click.stop>
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Map new departments</h3>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ $mappingBatch->source_filename }} mentions departments Servora hasn't
                                seen. Choose the Sales Category each belongs to — the choice is remembered,
                                so future uploads apply on their own.
                            </p>
                        </div>
                        <div class="p-5 space-y-3">
                            @foreach ($mappingSelections as $dept => $selected)
                                <div class="flex items-center gap-3" wire:key="map-{{ $dept }}">
                                    <span class="flex-1 text-sm font-medium text-gray-700">{{ $dept }}</span>
                                    <x-icon name="arrow-right" size="h-4 w-4" class="text-gray-400 flex-shrink-0" />
                                    <select wire:model="mappingSelections.{{ $dept }}"
                                            class="flex-1 text-sm rounded-lg border-gray-300">
                                        <option value="">— Choose category —</option>
                                        @foreach ($salesCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach

                            @php
                                $suggested = collect($mappingBatch->parsed_summary['suggestions'] ?? []);
                            @endphp
                            @if ($suggested->isNotEmpty())
                                <p class="text-xs text-gray-500">
                                    Pre-selected where Servora could guess from the name — check before saving.
                                </p>
                            @endif

                            <x-input-error :messages="$errors->get('mapping')" class="mt-1" />
                        </div>
                        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
                            <button type="button" wire:click="closeMapping" class="btn-secondary">Cancel</button>
                            <button type="button" wire:click="saveMappings" class="btn-primary">Save &amp; apply batch</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
