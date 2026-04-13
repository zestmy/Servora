<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Coupons</h2>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New Coupon
        </button>
    </div>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="Search code or description..."
               class="w-full md:w-80 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">Code</th>
                    <th class="px-5 py-3 text-left">Plan</th>
                    <th class="px-5 py-3 text-left">Grants</th>
                    <th class="px-5 py-3 text-center">Used</th>
                    <th class="px-5 py-3 text-left">Expires</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center w-28">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($coupons as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="font-mono font-bold text-gray-800">{{ $c->code }}</div>
                            @if ($c->description)
                                <div class="text-xs text-gray-400 mt-0.5">{{ $c->description }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $c->plan?->name ?? '— any —' }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded">
                                {{ $c->grantLabel() }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center text-gray-700">
                            {{ $c->redeemed_count }}{{ $c->max_redemptions ? ' / ' . $c->max_redemptions : '' }}
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs">
                            {{ $c->expires_at?->format('d M Y') ?? 'Never' }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if (! $c->is_active)
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded-full font-medium">Inactive</span>
                            @elseif ($c->isExpired())
                                <span class="px-2 py-0.5 bg-red-50 text-red-600 text-xs rounded-full font-medium">Expired</span>
                            @elseif ($c->isExhausted())
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-xs rounded-full font-medium">Used up</span>
                            @else
                                <span class="px-2 py-0.5 bg-green-50 text-green-700 text-xs rounded-full font-medium">Active</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            <button wire:click="toggleActive({{ $c->id }})" class="text-xs text-gray-500 hover:text-gray-700 mr-1" title="Toggle active">
                                {{ $c->is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button wire:click="openEdit({{ $c->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-1">Edit</button>
                            <button wire:click="delete({{ $c->id }})" wire:confirm="Delete {{ $c->code }}? This also removes redemption records."
                                    class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No coupons yet. Create one to grant free subscriptions.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($coupons->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $coupons->links() }}</div>
        @endif
    </div>

    {{-- Modal --}}
    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" x-data>
        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10 p-6 mt-8 mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-5">{{ $editingId ? 'Edit' : 'New' }} Coupon</h3>

            <div class="space-y-4">
                {{-- Code --}}
                <div>
                    <label class="text-sm font-medium text-gray-700">Coupon Code *</label>
                    <div class="flex gap-2 mt-1">
                        <input type="text" wire:model="code" class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="button" wire:click="generateCode" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Generate</button>
                    </div>
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>

                {{-- Description --}}
                <div>
                    <label class="text-sm font-medium text-gray-700">Description</label>
                    <input type="text" wire:model="description" placeholder="e.g. Early-bird promo 2026"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                {{-- Plan --}}
                <div>
                    <label class="text-sm font-medium text-gray-700">Plan</label>
                    <select wire:model="plan_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Use existing plan or first available —</option>
                        @foreach ($plans as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Leave blank to keep subscriber's current plan, or pick one to grant with this coupon.</p>
                </div>

                {{-- Grant Type + Value --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Grant *</label>
                        <select wire:model.live="grant_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="days">Days</option>
                            <option value="months">Months</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Duration {{ $grant_type === 'lifetime' ? '' : '*' }}</label>
                        <input type="number" wire:model="grant_value" min="1"
                               @if ($grant_type === 'lifetime') disabled placeholder="—" @endif
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100" />
                    </div>
                </div>

                {{-- Max Redemptions + Expires --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Max Redemptions</label>
                        <input type="number" wire:model="max_redemptions" min="1" placeholder="Unlimited"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Coupon Expiry</label>
                        <input type="date" wire:model="expires_at"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                </div>

                {{-- Active --}}
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 font-medium">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <button wire:click="closeModal" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button wire:click="save" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    {{ $editingId ? 'Update' : 'Create' }} Coupon
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
