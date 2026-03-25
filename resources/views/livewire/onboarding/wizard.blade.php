<div class="max-w-2xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Welcome to Servora!</h1>
        <p class="text-sm text-gray-500 mt-1">Let's get your account set up. This only takes a couple of minutes.</p>
        @if ($plan)
            <p class="text-xs text-indigo-600 font-medium mt-2">{{ $plan->name }} Plan — {{ $plan->trial_days }}-day free trial</p>
        @endif
    </div>

    {{-- Step Progress --}}
    <div class="flex items-center justify-center gap-2 mb-8">
        @foreach (\App\Models\OnboardingStep::STEPS as $i => $step)
            @php
                $stepData = $steps[$step] ?? null;
                $isComplete = $stepData?->isComplete();
                $isCurrent = $currentStep === $step;
            @endphp
            <div class="flex items-center gap-2">
                <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition
                    {{ $isComplete ? 'bg-green-500 text-white' : ($isCurrent ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                    @if ($isComplete)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                @if ($i < count(\App\Models\OnboardingStep::STEPS) - 1)
                    <div class="w-8 h-0.5 {{ $isComplete ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step Content --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">

        {{-- Step 1: Company Details --}}
        @if ($currentStep === 'company_details')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Company Details</h2>
            <p class="text-xs text-gray-400 mb-5">Add your business contact info and preferred currency.</p>

            <form wire:submit="saveCompanyDetails" class="space-y-4">
                <div>
                    <x-input-label for="ob_phone" value="Phone Number" />
                    <x-text-input id="ob_phone" wire:model="company_phone" type="text" class="mt-1 block w-full" placeholder="+60-3-1234-5678" />
                </div>
                <div>
                    <x-input-label for="ob_address" value="Business Address" />
                    <textarea id="ob_address" wire:model="company_address" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="123 Jalan Maju, KL"></textarea>
                </div>
                <div>
                    <x-input-label for="ob_currency" value="Currency" />
                    <select id="ob_currency" wire:model="currency"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="MYR">MYR — Malaysian Ringgit</option>
                        <option value="SGD">SGD — Singapore Dollar</option>
                        <option value="USD">USD — US Dollar</option>
                        <option value="THB">THB — Thai Baht</option>
                        <option value="IDR">IDR — Indonesian Rupiah</option>
                    </select>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-400 hover:text-gray-600 transition">Skip</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Continue
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 2: First Outlet --}}
        @if ($currentStep === 'first_outlet')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Your First Outlet</h2>
            <p class="text-xs text-gray-400 mb-5">Set up your main branch or outlet. You can add more later.</p>

            <form wire:submit="saveFirstOutlet" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="ob_outlet_name" value="Outlet Name *" />
                        <x-text-input id="ob_outlet_name" wire:model="outlet_name" type="text" class="mt-1 block w-full" placeholder="Main Branch" />
                        <x-input-error :messages="$errors->get('outlet_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ob_outlet_code" value="Code *" />
                        <x-text-input id="ob_outlet_code" wire:model="outlet_code" type="text" class="mt-1 block w-full" placeholder="MAIN" />
                        <x-input-error :messages="$errors->get('outlet_code')" class="mt-1" />
                    </div>
                </div>
                <div>
                    <x-input-label for="ob_outlet_phone" value="Outlet Phone" />
                    <x-text-input id="ob_outlet_phone" wire:model="outlet_phone" type="text" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label for="ob_outlet_addr" value="Outlet Address" />
                    <textarea id="ob_outlet_addr" wire:model="outlet_address" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-400 hover:text-gray-600 transition">Skip</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Continue
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 3: Invite Team --}}
        @if ($currentStep === 'invite_team')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Invite Your Team</h2>
            <p class="text-xs text-gray-400 mb-5">Add team members now or skip and do it later from Settings.</p>

            <form wire:submit="saveInviteTeam" class="space-y-4">
                @foreach ($invites as $index => $invite)
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-1 grid grid-cols-3 gap-2">
                            <div>
                                <x-text-input wire:model="invites.{{ $index }}.name" type="text" class="block w-full text-sm" placeholder="Name" />
                                <x-input-error :messages="$errors->get('invites.' . $index . '.name')" class="mt-0.5" />
                            </div>
                            <div>
                                <x-text-input wire:model="invites.{{ $index }}.email" type="email" class="block w-full text-sm" placeholder="Email" />
                                <x-input-error :messages="$errors->get('invites.' . $index . '.email')" class="mt-0.5" />
                            </div>
                            <div>
                                <select wire:model="invites.{{ $index }}.role"
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="Staff">Staff</option>
                                    <option value="Outlet Manager">Outlet Manager</option>
                                    <option value="Company Admin">Company Admin</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" wire:click="removeInvite({{ $index }})"
                                class="mt-1 text-red-400 hover:text-red-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach

                <button type="button" wire:click="addInvite"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
                    + Add team member
                </button>

                @if (empty($invites))
                    <p class="text-xs text-gray-400 py-4 text-center">No team members added yet. Click above to add, or skip this step.</p>
                @endif

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-400 hover:text-gray-600 transition">Skip</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ empty($invites) ? 'Skip' : 'Invite & Continue' }}
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 4: Explore Features --}}
        @if ($currentStep === 'explore_features')
            <h2 class="text-base font-semibold text-gray-800 mb-1">You're All Set!</h2>
            <p class="text-xs text-gray-400 mb-5">Here's what you can do with Servora:</p>

            <div class="grid grid-cols-2 gap-3 mb-6">
                @php
                    $featureCards = [
                        ['icon' => '🥕', 'title' => 'Ingredients', 'desc' => 'Manage your raw materials and track costs'],
                        ['icon' => '📋', 'title' => 'Recipes', 'desc' => 'Build recipes with automatic costing'],
                        ['icon' => '🛒', 'title' => 'Purchasing', 'desc' => 'Create POs and track deliveries'],
                        ['icon' => '💰', 'title' => 'Sales', 'desc' => 'Record daily sales and track revenue'],
                        ['icon' => '📦', 'title' => 'Inventory', 'desc' => 'Stock takes, wastage, and transfers'],
                        ['icon' => '📊', 'title' => 'Reports', 'desc' => 'Cost summaries and P&L reports'],
                    ];
                @endphp
                @foreach ($featureCards as $card)
                    <div class="p-3 rounded-lg border border-gray-100 hover:border-indigo-200 transition">
                        <span class="text-lg">{{ $card['icon'] }}</span>
                        <p class="text-sm font-medium text-gray-800 mt-1">{{ $card['title'] }}</p>
                        <p class="text-xs text-gray-400">{{ $card['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            <button wire:click="finishOnboarding"
                    class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition">
                Go to Dashboard
            </button>
        @endif
    </div>
</div>
