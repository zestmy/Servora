<x-layouts.marketing :title="'Affiliate Dashboard'">
<div class="max-w-4xl mx-auto px-4 py-12">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ $affiliate->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Your referral dashboard</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">{{ $affiliate->email }}</span>
            <form method="POST" action="{{ route('affiliate.logout') }}">
                @csrf
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium transition">Logout</button>
            </form>
        </div>
    </div>

    @if ($referralCode)
        {{-- Referral Link --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Your Referral Link</h2>
            <div class="flex items-center gap-2" x-data="{ copied: false }">
                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5">
                    <p class="text-sm text-gray-800 font-mono truncate" id="ref-url">{{ $referralCode->url }}</p>
                </div>
                <button type="button"
                        @click="navigator.clipboard.writeText(document.getElementById('ref-url').textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)"
                        class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                    <span x-show="!copied">Copy Link</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Code: <span class="font-mono font-medium text-gray-600">{{ $referralCode->code }}</span></p>
        </div>

        {{-- Stats --}}
        @if ($referralStats)
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($referralStats['clicks']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Clicks</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($referralStats['signups']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Signups</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($referralStats['conversions']) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Conversions</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-amber-600">RM {{ number_format($referralStats['pending'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Pending</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600">RM {{ number_format($referralStats['paid'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Paid Out</p>
                </div>
            </div>
        @endif

        {{-- Bank Details --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Payout Details</h2>
            @if ($affiliate->bank_name && $affiliate->bank_account_number)
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-500">Bank</p>
                        <p class="font-medium text-gray-800">{{ $affiliate->bank_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Account Name</p>
                        <p class="font-medium text-gray-800">{{ $affiliate->bank_account_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Account Number</p>
                        <p class="font-medium text-gray-800">{{ $affiliate->bank_account_number }}</p>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('affiliate.update-bank') }}" class="grid grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <x-input-label for="bank_name" value="Bank Name" />
                        <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" placeholder="e.g. Maybank" :value="$affiliate->bank_name" />
                    </div>
                    <div>
                        <x-input-label for="bank_account_name" value="Account Name" />
                        <x-text-input id="bank_account_name" name="bank_account_name" type="text" class="mt-1 block w-full" :value="$affiliate->bank_account_name" />
                    </div>
                    <div>
                        <x-input-label for="bank_account_number" value="Account Number" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="block w-full" :value="$affiliate->bank_account_number" />
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">Save</button>
                        </div>
                    </div>
                </form>
                <p class="text-xs text-amber-600 mt-2">Add your bank details so we can pay your commissions.</p>
            @endif
        </div>

        {{-- Referrals & Commissions --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Referrals --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Referrals</h2>
                @forelse ($referrals as $ref)
                    <div class="flex items-center justify-between py-2 {{ !$loop->first ? 'border-t border-gray-100' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $ref->referredCompany?->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-gray-400">{{ $ref->created_at->format('d M Y') }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $ref->status === 'paid' ? 'bg-green-100 text-green-700' : ($ref->status === 'converted' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ucfirst(str_replace('_', ' ', $ref->status)) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-4 text-center">No referrals yet. Share your link!</p>
                @endforelse
            </div>

            {{-- Commissions --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Commissions</h2>
                @forelse ($commissions as $comm)
                    <div class="flex items-center justify-between py-2 {{ !$loop->first ? 'border-t border-gray-100' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-800">RM {{ number_format($comm->amount, 2) }}</p>
                            <p class="text-xs text-gray-400">{{ $comm->referral?->referredCompany?->name }} — {{ $comm->created_at->format('d M Y') }}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $comm->status === 'paid' ? 'bg-green-100 text-green-700' : ($comm->status === 'approved' ? 'bg-blue-100 text-blue-700' : ($comm->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')) }}">
                            {{ ucfirst($comm->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-4 text-center">Commissions appear here when referrals convert.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
</x-layouts.marketing>
