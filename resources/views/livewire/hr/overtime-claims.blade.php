<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-gray-800">Overtime Claims</h1>
            <p class="text-xs text-gray-400 mt-0.5">Submit and manage staff overtime claims</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New OT Claim
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Approved Hours (Month)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($totalHoursMonth, 1) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Pending Approval</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Approved (Month)</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $approvedCount }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap gap-3">
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="From" />
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="To" />
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Employee</th>
                        <th class="px-4 py-3 text-center">Time</th>
                        <th class="px-4 py-3 text-center">Hours</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($claims as $claim)
                        <tr wire:key="claim-{{ $claim->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap">
                                {{ $claim->claim_date->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $claim->employee?->name ?? '—' }}
                                <p class="text-[10px] text-gray-400">by {{ $claim->submitter?->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600 whitespace-nowrap">
                                {{ substr($claim->ot_time_start, 0, 5) }} – {{ substr($claim->ot_time_end, 0, 5) }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-800">
                                {{ number_format($claim->total_ot_hours, 1) }}h
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full
                                    {{ match($claim->ot_type) {
                                        'public_holiday' => 'bg-red-50 text-red-600',
                                        'rest_day'       => 'bg-amber-50 text-amber-600',
                                        default          => 'bg-gray-100 text-gray-600',
                                    } }}">
                                    {{ $claim->otTypeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate" title="{{ $claim->reason }}">
                                {{ $claim->reason }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full
                                    {{ match($claim->status) {
                                        'draft'     => 'bg-gray-100 text-gray-600',
                                        'submitted' => 'bg-amber-100 text-amber-700',
                                        'approved'  => 'bg-green-100 text-green-700',
                                        'rejected'  => 'bg-red-100 text-red-600',
                                        default     => 'bg-gray-100 text-gray-500',
                                    } }}">
                                    {{ $claim->status === 'submitted' ? 'Pending' : ucfirst($claim->status) }}
                                </span>
                                @if ($claim->status === 'rejected' && $claim->rejected_reason)
                                    <p class="text-[10px] text-red-400 mt-0.5">{{ Str::limit($claim->rejected_reason, 30) }}</p>
                                @endif
                                @if ($claim->status === 'approved' && $claim->approver)
                                    <p class="text-[10px] text-gray-400 mt-0.5">by {{ $claim->approver->name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($claim->status === 'draft')
                                        <button wire:click="openEdit({{ $claim->id }})" class="text-indigo-500 hover:text-indigo-700 text-xs font-medium">Edit</button>
                                        <button wire:click="submitClaim({{ $claim->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Submit</button>
                                        <button wire:click="deleteClaim({{ $claim->id }})" wire:confirm="Delete this OT claim?" class="text-red-400 hover:text-red-600 text-xs font-medium">Delete</button>
                                    @endif
                                    @if ($claim->status === 'submitted' && $isApprover)
                                        <button wire:click="approveClaim({{ $claim->id }})" class="text-green-600 hover:text-green-800 text-xs font-medium">Approve</button>
                                        <button wire:click="openReject({{ $claim->id }})" class="text-red-500 hover:text-red-700 text-xs font-medium">Reject</button>
                                    @endif
                                    @if ($claim->status === 'rejected')
                                        <button wire:click="deleteClaim({{ $claim->id }})" wire:confirm="Delete this rejected claim?" class="text-red-400 hover:text-red-600 text-xs font-medium">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                <p class="text-2xl mb-2">&#128337;</p>
                                <p class="font-medium">No overtime claims found</p>
                                <p class="text-xs mt-1">Click "+ New OT Claim" to submit one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($claims->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $claims->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">
                    {{ $editingId ? 'Edit OT Claim' : 'New OT Claim' }}
                </h3>

                <div class="space-y-4">
                    {{-- Employee --}}
                    <div>
                        <x-input-label for="ot_employee" value="Employee *" />
                        <select id="ot_employee" wire:model="employee_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select Employee —</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-1" />
                    </div>

                    {{-- Date --}}
                    <div>
                        <x-input-label for="ot_date" value="Date *" />
                        <x-text-input id="ot_date" wire:model="claim_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('claim_date')" class="mt-1" />
                    </div>

                    {{-- Time Start / End --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ot_start" value="OT Start *" />
                            <x-text-input id="ot_start" wire:model.live="ot_time_start" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ot_time_start')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ot_end" value="OT End *" />
                            <x-text-input id="ot_end" wire:model.live="ot_time_end" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ot_time_end')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Total Hours --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ot_hours" value="Total OT Hours *" />
                            <x-text-input id="ot_hours" wire:model="total_ot_hours" type="number" step="0.25" min="0.25" max="24"
                                          class="mt-1 block w-full" />
                            <p class="text-[10px] text-gray-400 mt-0.5">Auto-calculated, editable override</p>
                            <x-input-error :messages="$errors->get('total_ot_hours')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ot_type" value="OT Type *" />
                            <select id="ot_type" wire:model="ot_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="normal_day">Normal Day</option>
                                <option value="public_holiday">Public Holiday</option>
                                <option value="rest_day">Rest Day</option>
                            </select>
                            <x-input-error :messages="$errors->get('ot_type')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Reason --}}
                    <div>
                        <x-input-label for="ot_reason" value="Reason for OT *" />
                        <textarea id="ot_reason" wire:model="reason" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Describe the reason for overtime..."></textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="save('submit')"
                            class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                        Save & Submit
                    </button>
                    <button wire:click="save"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Draft
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showRejectModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Reject OT Claim</h3>
                <div>
                    <x-input-label for="reject_reason" value="Reason for Rejection *" />
                    <textarea id="reject_reason" wire:model="rejected_reason" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Explain why this claim is being rejected..."></textarea>
                    <x-input-error :messages="$errors->get('rejected_reason')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showRejectModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="rejectClaim"
                            class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                        Reject Claim
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
