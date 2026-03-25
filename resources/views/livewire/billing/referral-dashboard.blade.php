<div>
    @if (session()->has('referral_success'))
        <div wire:key="ref-flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('referral_success') }}
        </div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Refer & Earn</h1>
    <p class="text-xs text-gray-400 mb-6">Share Servora with other F&B businesses and earn commission on every signup.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Referral Link & Stats --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                @if ($referralCode)
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">Your Referral Link</h3>
                    <div class="flex items-center gap-2 mb-4" x-data="{ copied: @entangle('copiedLink') }">
                        <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5">
                            <p class="text-sm text-gray-800 font-mono truncate" id="referral-url">{{ $referralCode->url }}</p>
                        </div>
                        <button type="button"
                                x-on:click="navigator.clipboard.writeText(document.getElementById('referral-url').textContent.trim()); copied = true; setTimeout(() => copied = false, 2000); $wire.markCopied()"
                                class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                            <span x-show="!copied">Copy Link</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-700">{{ $referralCode->code }}</span>
                        <span class="text-gray-300">|</span>
                        <span>Created {{ $referralCode->created_at->format('d M Y') }}</span>
                    </div>

                    {{-- Stats --}}
                    @if ($referralStats)
                        <div class="grid grid-cols-4 gap-3 mt-5 pt-5 border-t border-gray-100">
                            <div class="text-center">
                                <p class="text-xl font-bold text-gray-900">{{ number_format($referralStats['clicks']) }}</p>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Clicks</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-bold text-blue-600">{{ number_format($referralStats['signups']) }}</p>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Signups</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-bold text-green-600">{{ number_format($referralStats['conversions']) }}</p>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Converted</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-bold text-indigo-600">RM {{ number_format($referralStats['earned'], 2) }}</p>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Earned</p>
                            </div>
                        </div>
                    @endif

                    {{-- Referral List --}}
                    @if ($referralStats && $referralStats['referrals']->isNotEmpty())
                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Your Referrals</h4>
                            <div class="space-y-2">
                                @foreach ($referralStats['referrals'] as $ref)
                                    <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ $ref->referredCompany?->name ?? 'Unknown' }}</p>
                                            <p class="text-xs text-gray-400">{{ $ref->created_at->format('d M Y') }}</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $ref->status === 'paid' ? 'bg-green-100 text-green-700' : ($ref->status === 'converted' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                                            {{ ucfirst(str_replace('_', ' ', $ref->status)) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    {{-- No referral code yet --}}
                    <div class="text-center py-6">
                        <div class="w-14 h-14 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Start Referring</h3>
                        <p class="text-xs text-gray-500 mb-4 max-w-sm mx-auto">
                            Get your unique referral link and earn commission when other F&B businesses sign up for Servora.
                        </p>
                        <button wire:click="generateReferralCode"
                                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Generate My Referral Link
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- How It Works --}}
        <div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">How It Works</h3>
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-xs font-bold text-indigo-600">1</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Share your link</p>
                            <p class="text-xs text-gray-400">Send your referral link to other F&B business owners</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-xs font-bold text-indigo-600">2</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">They sign up</p>
                            <p class="text-xs text-gray-400">When they register using your link, it's tracked automatically</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-xs font-bold text-indigo-600">3</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Earn commission</p>
                            <p class="text-xs text-gray-400">When they subscribe, you earn commission on their payment</p>
                        </div>
                    </div>
                </div>
            </div>

            <a href="{{ route('referral.program') }}" class="block mt-4 text-center text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                View full referral program details
            </a>
        </div>
    </div>
</div>
