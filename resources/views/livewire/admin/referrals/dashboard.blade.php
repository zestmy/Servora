<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <h1 class="text-lg font-bold text-gray-800">Referral Dashboard</h1>
            <p class="text-xs text-gray-400 mt-0.5">Track referrals, conversions, and commissions.</p>
        </div>
        <a href="{{ route('admin.referrals.programs') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
            Manage Programs
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Total Referrals</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalReferrals) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Conversions</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($totalConversions) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Pending Commission</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">RM {{ number_format($totalPending, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500 font-medium">Total Paid</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">RM {{ number_format($totalPaid, 2) }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-4 border-b border-gray-200 mb-4">
        <button wire:click="$set('tab', 'referrals')" class="pb-2 text-sm font-medium border-b-2 transition {{ $tab === 'referrals' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Referrals</button>
        <button wire:click="$set('tab', 'commissions')" class="pb-2 text-sm font-medium border-b-2 transition {{ $tab === 'commissions' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Commissions</button>
        <button wire:click="$set('tab', 'codes')" class="pb-2 text-sm font-medium border-b-2 transition {{ $tab === 'codes' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Referral Codes</button>
    </div>

    {{-- Referrals Tab --}}
    @if ($tab === 'referrals')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Referred Company</th>
                        <th class="px-4 py-3 text-left">Referrer Code</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($referrals as $ref)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $ref->referredCompany?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $ref->referralCode?->code ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $ref->status === 'paid' ? 'bg-green-100 text-green-700' : ($ref->status === 'converted' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ ucfirst(str_replace('_', ' ', $ref->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $ref->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-gray-400">No referrals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($referrals->hasPages()) <div class="mt-4">{{ $referrals->links() }}</div> @endif
    @endif

    {{-- Commissions Tab --}}
    @if ($tab === 'commissions')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-left">Referrer</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($commissions as $comm)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $comm->referral?->referredCompany?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $comm->referral?->referralCode?->code ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">RM {{ number_format($comm->amount, 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $comm->status === 'paid' ? 'bg-green-100 text-green-700' : ($comm->status === 'approved' ? 'bg-blue-100 text-blue-700' : ($comm->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')) }}">
                                    {{ ucfirst($comm->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if ($comm->status === 'pending')
                                        <button wire:click="approveCommission({{ $comm->id }})" class="text-green-500 hover:text-green-700 text-xs font-medium">Approve</button>
                                        <button wire:click="rejectCommission({{ $comm->id }})" class="text-red-400 hover:text-red-600 text-xs font-medium">Reject</button>
                                    @elseif ($comm->status === 'approved')
                                        <button wire:click="markPaid({{ $comm->id }})" class="text-indigo-500 hover:text-indigo-700 text-xs font-medium">Mark Paid</button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">No commissions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($commissions->hasPages()) <div class="mt-4">{{ $commissions->links() }}</div> @endif
    @endif

    {{-- Codes Tab --}}
    @if ($tab === 'codes')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-center">Clicks</th>
                        <th class="px-4 py-3 text-center">Signups</th>
                        <th class="px-4 py-3 text-center">Conversions</th>
                        <th class="px-4 py-3 text-center">Conv. Rate</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($codes as $code)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-gray-800">{{ $code->code }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($code->total_clicks) }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($code->total_signups) }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($code->total_conversions) }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ $code->total_clicks > 0 ? round($code->total_conversions / $code->total_clicks * 100, 1) . '%' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $code->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $code->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No referral codes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
