<div class="max-w-2xl mx-auto px-4 py-8">
    {{-- The first screen a new customer sees after signing up, so it gets a
         little ceremony: a chef's hat that bounces in, kitchen names for
         each step, and confetti on the last one. Looks only; the forms and
         their wire: bindings are unchanged. Motion stops under
         prefers-reduced-motion. --}}
    <style>
        .ob-hat { animation: ob-bob 2.8s ease-in-out infinite; transform-origin: 50% 100%; }
        @keyframes ob-bob {
            0%, 100% { transform: translateY(0) rotate(0); }
            25% { transform: translateY(-4px) rotate(-5deg); }
            50% { transform: translateY(0) rotate(0); }
            75% { transform: translateY(-3px) rotate(4deg); }
        }
        .ob-spark { animation: ob-twinkle 2.2s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
        .ob-spark:nth-of-type(2) { animation-delay: .7s; }
        .ob-spark:nth-of-type(3) { animation-delay: 1.4s; }
        @keyframes ob-twinkle { 0%, 100% { opacity: .2; transform: scale(.6); } 50% { opacity: 1; transform: scale(1); } }
        .ob-current { animation: ob-pulse 2s ease-out infinite; }
        @keyframes ob-pulse { 0% { box-shadow: 0 0 0 0 rgb(9 98 239 / .45); } 100% { box-shadow: 0 0 0 10px rgb(9 98 239 / 0); } }
        .ob-confetti { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
        .ob-confetti i { position: absolute; top: -12px; width: 8px; height: 12px; border-radius: 2px; opacity: 0; animation: ob-fall 2.6s ease-in forwards; }
        @keyframes ob-fall {
            0% { opacity: 1; transform: translateY(0) rotate(0); }
            100% { opacity: 0; transform: translateY(260px) rotate(540deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ob-hat, .ob-spark, .ob-current { animation: none; }
            .ob-confetti { display: none; }
        }
    </style>

    @php
        // A kitchen name for each step, shown above its heading.
        $stepFlavour = [
            'company_details'  => 'Mise en place',
            'first_outlet'     => 'Fire up the stove',
            'invite_team'      => 'Call in the brigade',
            'explore_features' => 'Service!',
        ];
        $stepNumber = array_search($currentStep, \App\Models\OnboardingStep::STEPS, true);
    @endphp

    {{-- Header --}}
    <div class="text-center mb-8">
        <svg class="mx-auto mb-3 h-16 w-16" viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <g class="ob-hat">
                <path d="M20 40 C10 40 8 26 18 24 C18 14 30 10 34 18 C40 10 54 14 50 26 C58 28 56 40 46 40 Z" fill="white" stroke="#0962ef" stroke-width="2.5" stroke-linejoin="round" />
                <rect x="20" y="40" width="26" height="12" rx="3" fill="#0962ef" />
                <path d="M26 44 v4 M33 44 v4 M40 44 v4" stroke="white" stroke-width="2" stroke-linecap="round" opacity=".6" />
            </g>
            <path class="ob-spark" d="M8 12 l2 -5 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 z" fill="#f59e0b" />
            <path class="ob-spark" d="M54 6 l1.5 -3.5 l1.5 3.5 l3.5 1.5 l-3.5 1.5 l-1.5 3.5 l-1.5 -3.5 l-3.5 -1.5 z" fill="#f59e0b" />
            <path class="ob-spark" d="M56 46 l1.2 -3 l1.2 3 l3 1.2 l-3 1.2 l-1.2 3 l-1.2 -3 l-3 -1.2 z" fill="#0962ef" />
        </svg>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-600">The doors are open</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">Welcome to Servora!</h1>
        <p class="text-sm text-gray-600 mt-1">Let&rsquo;s get your kitchen set up. Four quick steps, a couple of minutes, then you&rsquo;re on the pass.</p>
        @if ($plan)
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
                {{ $plan->name }} Plan &middot; {{ $plan->trial_days }}-day free trial
            </p>
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
                    {{ $isComplete ? 'bg-success-500 text-white' : ($isCurrent ? 'bg-brand-600 text-white ob-current' : 'bg-gray-200 text-gray-500') }}"
                     title="{{ \App\Models\OnboardingStep::LABELS[$step] }}">
                    @if ($isComplete)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                @if ($i < count(\App\Models\OnboardingStep::STEPS) - 1)
                    <div class="w-8 h-0.5 {{ $isComplete ? 'bg-success-400' : 'bg-gray-200' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step Content --}}
    <div class="card relative p-6">
        @if ($stepNumber !== false)
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-brand-600">
                Step {{ $stepNumber + 1 }} of {{ count(\App\Models\OnboardingStep::STEPS) }} &middot; {{ $stepFlavour[$currentStep] ?? '' }}
            </p>
        @endif

        {{-- Step 1: Company Details --}}
        @if ($currentStep === 'company_details')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Company Details</h2>
            <p class="text-xs text-gray-600 mb-5">Add your business contact info and preferred currency.</p>

            <form wire:submit="saveCompanyDetails" class="space-y-4">
                <div>
                    <x-input-label for="ob_phone" value="Phone Number" />
                    <x-text-input id="ob_phone" wire:model="company_phone" type="text" class="mt-1 block w-full" placeholder="+60-3-1234-5678" />
                </div>
                <div>
                    <x-input-label for="ob_address" value="Business Address" />
                    <textarea id="ob_address" wire:model="company_address" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"
                              placeholder="123 Jalan Maju, KL"></textarea>
                </div>
                <div>
                    <x-input-label for="ob_currency" value="Currency" />
                    <select id="ob_currency" wire:model="currency"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="MYR">MYR — Malaysian Ringgit</option>
                        <option value="SGD">SGD — Singapore Dollar</option>
                        <option value="USD">USD — US Dollar</option>
                        <option value="THB">THB — Thai Baht</option>
                        <option value="IDR">IDR — Indonesian Rupiah</option>
                    </select>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-600 hover:text-gray-900 transition">Skip</button>
                    <button type="submit" class="btn-primary">
                        Continue
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 2: First Outlet --}}
        @if ($currentStep === 'first_outlet')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Your First Outlet</h2>
            <p class="text-xs text-gray-600 mb-5">Set up your main branch or outlet. You can add more later.</p>

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
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-600 hover:text-gray-900 transition">Skip</button>
                    <button type="submit" class="btn-primary">
                        Continue
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 3: Invite Team --}}
        @if ($currentStep === 'invite_team')
            <h2 class="text-base font-semibold text-gray-800 mb-1">Invite Your Team</h2>
            <p class="text-xs text-gray-600 mb-5">Add team members now or skip and do it later from Settings.</p>

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
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="Staff">Staff</option>
                                    <option value="Outlet Manager">Outlet Manager</option>
                                    <option value="Company Admin">Company Admin</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" wire:click="removeInvite({{ $index }})"
                                class="mt-1 text-danger-400 hover:text-danger-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach

                <button type="button" wire:click="addInvite"
                        class="text-sm text-brand-600 hover:text-brand-800 font-medium transition">
                    + Add team member
                </button>

                @if (empty($invites))
                    <p class="text-xs text-gray-600 py-4 text-center">No team members added yet. Click above to add, or skip this step.</p>
                @endif

                <div class="flex items-center justify-between pt-4">
                    <button type="button" wire:click="skipStep" class="text-sm text-gray-600 hover:text-gray-900 transition">Skip</button>
                    <button type="submit" class="btn-primary">
                        {{ empty($invites) ? 'Skip' : 'Invite & Continue' }}
                    </button>
                </div>
            </form>
        @endif

        {{-- Step 4: Explore Features --}}
        @if ($currentStep === 'explore_features')
            {{-- A short burst of confetti when the last step opens. --}}
            <div class="ob-confetti" aria-hidden="true">
                @foreach ([['8%','#0962ef',0],['18%','#f59e0b',.15],['30%','#10b981',.05],['42%','#ef4444',.25],['55%','#0962ef',.1],['66%','#f59e0b',.3],['78%','#10b981',.2],['90%','#ef4444',.05]] as [$left, $colour, $delay])
                    <i style="left: {{ $left }}; background: {{ $colour }}; animation-delay: {{ $delay }}s"></i>
                @endforeach
            </div>
            <h2 class="text-base font-semibold text-gray-800 mb-1">You're All Set! 🎉</h2>
            <p class="text-xs text-gray-600 mb-5">Here's what you can do with Servora:</p>

            <div class="grid grid-cols-2 gap-3 mb-6">
                @php
                    $featureCards = [
                        ['icon' => '🥕', 'title' => 'Products', 'desc' => 'Manage your raw materials and track costs'],
                        ['icon' => '📋', 'title' => 'Recipes', 'desc' => 'Build recipes with automatic costing'],
                        ['icon' => '🛒', 'title' => 'Purchasing', 'desc' => 'Create POs and track deliveries'],
                        ['icon' => '💰', 'title' => 'Sales', 'desc' => 'Record daily sales and track revenue'],
                        ['icon' => '📦', 'title' => 'Inventory', 'desc' => 'Stock takes, wastage, and transfers'],
                        ['icon' => '📊', 'title' => 'Reports', 'desc' => 'Cost summaries and P&L reports'],
                    ];
                @endphp
                @foreach ($featureCards as $card)
                    <div class="p-3 rounded-lg border border-gray-100 hover:border-brand-200 transition">
                        <span class="text-lg">{{ $card['icon'] }}</span>
                        <p class="text-sm font-medium text-gray-800 mt-1">{{ $card['title'] }}</p>
                        <p class="text-xs text-gray-600">{{ $card['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            <button wire:click="finishOnboarding"
                    class="btn-primary w-full py-3">
                Open the kitchen: go to Dashboard &rarr;
            </button>
        @endif
    </div>
</div>
