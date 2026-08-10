<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tool</p>
        <h1 class="display-2 mt-2">Food Cost Percentage Calculator</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            What a dish costs you, what it sells for, and whether that leaves enough behind.
            Nothing to sign up for.
        </p>
    </div>

    <div class="card mt-8 p-6">
        <label for="dish" class="label">Dish name <span class="text-gray-500">(optional)</span></label>
        <input id="dish" type="text" wire:model="dishName" class="input mt-1" placeholder="e.g. Chicken Chop" />

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="cost" class="label">Cost to make</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-sm text-gray-600">RM</span>
                    <input id="cost" type="number" min="0" step="0.01" wire:model.live.debounce.400ms="cost"
                           class="input tabular-nums" />
                </div>
            </div>
            <div>
                <label for="price" class="label">Menu price</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-sm text-gray-600">RM</span>
                    <input id="price" type="number" min="0" step="0.01" wire:model.live.debounce.400ms="price"
                           class="input tabular-nums" />
                </div>
            </div>
            <div>
                <label for="target" class="label">Target food cost</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="target" type="number" min="1" max="99" step="1" wire:model.live.debounce.400ms="target"
                           class="input tabular-nums" />
                    <span class="text-sm text-gray-600">%</span>
                </div>
            </div>
        </div>

        @if ($figures['actual'] !== null)
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="stat">
                    <p class="stat-label">Food cost</p>
                    <p class="stat-value tabular-nums
                        {{ $verdict['tone'] === 'good' ? 'text-success-600' : ($verdict['tone'] === 'bad' ? 'text-danger-600' : 'text-warning-600') }}">
                        {{ number_format($figures['actual'], 1) }}%
                    </p>
                </div>
                <div class="stat">
                    <p class="stat-label">Gross profit per dish</p>
                    <p class="stat-value tabular-nums">RM {{ number_format($figures['profit'], 2) }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Gross margin</p>
                    <p class="stat-value tabular-nums">{{ number_format($figures['margin'], 1) }}%</p>
                </div>
            </div>

            @if ($verdict)
                <div class="alert-{{ $verdict['tone'] === 'good' ? 'success' : ($verdict['tone'] === 'bad' ? 'danger' : 'warning') }} mt-4">
                    {{ $verdict['text'] }}
                </div>
            @endif

            {{-- Two ways to hit the target, because in a real kitchen only one
                 of them is usually available this week. --}}
            <div class="mt-6 rounded-panel border border-gray-200 bg-gray-50 p-5">
                <h2 class="text-sm font-semibold text-gray-800">
                    To hit {{ rtrim(rtrim(number_format($target, 1), '0'), '.') }}%
                </h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    @if ($figures['price_at_target'])
                        <p class="text-sm text-gray-700">
                            Charge <strong class="tabular-nums">RM {{ number_format($figures['price_at_target'], 2) }}</strong>
                            <span class="text-gray-600">for what it costs you now</span>
                        </p>
                    @endif
                    @if ($figures['cost_at_target'])
                        <p class="text-sm text-gray-700">
                            Or get the recipe down to
                            <strong class="tabular-nums">RM {{ number_format($figures['cost_at_target'], 2) }}</strong>
                            <span class="text-gray-600">at today's price</span>
                        </p>
                    @endif
                </div>
            </div>

            <div class="mt-6">
                <label for="sold" class="label">Sold per week <span class="text-gray-500">(optional)</span></label>
                <input id="sold" type="number" min="0" step="1" wire:model.live.debounce.400ms="soldPerWeek"
                       class="input mt-1 w-40 tabular-nums" />

                @if ($figures['monthly_profit'] !== null)
                    <p class="mt-3 text-sm text-gray-700">
                        That is
                        <strong class="tabular-nums">RM {{ number_format($figures['monthly_profit'], 2) }}</strong>
                        of gross profit a month from
                        <strong>{{ $dishName ?: 'this dish' }}</strong> alone.
                    </p>
                @endif
            </div>
        @else
            <div class="empty-state mt-8">
                <p class="font-medium text-gray-800">Enter a cost and a menu price</p>
                <p class="help mt-1">Everything else follows from those two numbers.</p>
            </div>
        @endif
    </div>

    <p class="mt-6 text-center text-sm text-gray-600">
        Don't know what a dish costs?
        <a href="{{ route('tools.recipe-cost') }}" class="text-brand-600 underline">Build it from real ingredient prices</a>
        &mdash; or let Servora cost every recipe you sell,
        <a href="{{ route('saas.register') }}" class="text-brand-600 underline">automatically</a>.
    </p>
</div>
