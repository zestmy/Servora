<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">Labels / Agents</p>
            <h1 class="page-title mt-1">Print agents</h1>
        </div>
        <div class="flex items-center gap-2">
            @if ($downloadUrl = \App\Models\PrintAgent::downloadUrl())
                <a href="{{ $downloadUrl }}" class="btn-secondary" download>
                    <span class="sm:hidden">Download</span>
                    <span class="hidden sm:inline">Download Agent v{{ \App\Models\PrintAgent::CURRENT_VERSION }}</span>
                </a>
            @endif
            <button wire:click="openCreate" class="btn-primary">
                <span class="sm:hidden">+ Add</span>
                <span class="hidden sm:inline">+ Add Agent</span>
            </button>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            A print agent is a small program on the outlet PC that prints labels sent from phones and
            tablets — no PrintNode subscription. Download the agent onto the PC, add an agent here,
            then enter its pairing code on the PC when the agent asks for it — the zip's README walks
            through the four steps.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Server address for the agent: <code class="px-1 py-0.5 bg-gray-100 rounded">{{ $this->serverUrl() }}</code>
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[720px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Agent</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left">Machine</th>
                        <th class="px-4 py-3 text-center w-28">Printers</th>
                        <th class="px-4 py-3 text-left w-44">Status</th>
                        <th class="px-4 py-3 text-center w-40">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agents as $agent)
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
                                                  title="The current install zip ships v{{ \App\Models\PrintAgent::CURRENT_VERSION }} — re-download and replace the exe on this PC">
                                                v{{ \App\Models\PrintAgent::CURRENT_VERSION }} available
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ $agent->printers === null ? '—' : count($agent->reportedPrinters()) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $agent->isOnline() ? 'badge-success' : ($agent->isRevoked() ? 'badge-neutral' : 'badge-warning') }}">
                                    {{ $agent->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @unless ($agent->isRevoked())
                                    <button wire:click="showCode({{ $agent->id }})"
                                            class="text-brand-600 hover:text-brand-800 text-xs"
                                            title="Show a pairing code to enter on the outlet PC">
                                        {{ $agent->isPaired() ? 'Re-pair' : 'Pairing code' }}
                                    </button>
                                    <button wire:click="revoke({{ $agent->id }})"
                                            data-confirm-delete="Revoke this agent? Its token stops working immediately and the PC will need a new pairing code. The row stays for the audit trail."
                                            class="ml-2 text-danger-500 hover:text-danger-700 text-xs">Revoke</button>
                                @else
                                    <span class="text-xs text-gray-400">Revoked {{ $agent->revoked_at?->diffForHumans() }}</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-600 text-sm">
                                No agents yet. Add one for each outlet PC that should print labels
                                sent from phones and tablets.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

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
                                       placeholder="e.g. Back-office PC" />
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
                                    The outlet this PC prints for. Chosen here, by you, before the machine
                                    is trusted — the agent cannot pick its own.
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

    {{-- Pairing code modal. Plain @if rather than an entangled toggle:
         the code is ephemeral component state, and a re-render on
         showCode/closeCode is exactly the lifetime it should have. --}}
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
                            <p>On the outlet PC, run the Servora Print Agent and enter:</p>
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
                                {{ \App\Models\PrintAgent::PAIRING_TTL_MINUTES }} minutes. If it runs out,
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
</div>
