<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-700">Price Alerts</h2>
        </div>
        <button wire:click="openCreate" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">+ New Alert</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($alerts->count() > 0)
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Ingredient</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-center">Alert Type</th>
                        <th class="px-4 py-3 text-center">Threshold</th>
                        <th class="px-4 py-3 text-center">Last Triggered</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($alerts as $alert)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $alert->ingredient?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $alert->supplier?->name ?? 'All suppliers' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                    {{ match($alert->alert_type) {
                                        'increase' => 'bg-red-50 text-red-600',
                                        'decrease' => 'bg-green-50 text-green-600',
                                        'threshold' => 'bg-amber-50 text-amber-600',
                                    } }}">
                                    {{ ucfirst($alert->alert_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600 text-xs">
                                @if ($alert->threshold_percent)
                                    {{ $alert->threshold_percent }}%
                                @elseif ($alert->threshold_amount)
                                    RM {{ number_format($alert->threshold_amount, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-400 text-xs">
                                {{ $alert->last_triggered_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActive({{ $alert->id }})"
                                        class="px-2 py-0.5 rounded text-xs font-medium cursor-pointer transition
                                        {{ $alert->is_active ? 'bg-green-50 text-green-600 hover:bg-green-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    {{ $alert->is_active ? 'Active' : 'Paused' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="openEdit({{ $alert->id }})" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button wire:click="delete({{ $alert->id }})" wire:confirm="Delete this alert?" class="text-sm text-red-500 hover:text-red-700 ml-2">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-8 text-center text-gray-400 text-sm">
                No price alerts configured. Click "+ New Alert" to monitor ingredient prices.
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showForm', false)"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">{{ $editId ? 'Edit' : 'New' }} Price Alert</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ingredient *</label>
                            <select wire:model="ingredient_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">— Select —</option>
                                @foreach ($ingredients as $ing)
                                    <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                @endforeach
                            </select>
                            @error('ingredient_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Supplier (optional)</label>
                            <select wire:model="supplier_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Leave blank to monitor across all suppliers</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Alert Type *</label>
                            <select wire:model="alert_type" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="increase">Price Increase</option>
                                <option value="decrease">Price Decrease</option>
                                <option value="threshold">Exceeds Threshold</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Threshold %</label>
                                <input type="number" step="0.01" min="0" max="100" wire:model="threshold_percent"
                                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 10" />
                                <p class="text-[11px] text-gray-400 mt-1">Alert when change exceeds this %</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Threshold Amount (RM)</label>
                                <input type="number" step="0.01" min="0" wire:model="threshold_amount"
                                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. 5.00" />
                                <p class="text-[11px] text-gray-400 mt-1">Or absolute amount threshold</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600" />
                            <span class="text-sm text-gray-600">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            {{ $editId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
