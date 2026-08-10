<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tool</p>
        <h1 class="display-2 mt-2">Recipe Cost Calculator</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            Build a recipe from real ingredient prices and see what it costs to make — and what it
            needs to sell for. No account, no spreadsheet.
        </p>
    </div>

    <div class="card mt-8 p-6">
        <label for="recipe-name" class="label">Recipe name</label>
        <input id="recipe-name" type="text" wire:model="recipeName" class="input mt-1"
               placeholder="e.g. Nasi Goreng Kampung" />

        <label for="ingredient-search" class="label mt-6">Add ingredients</label>
        <div class="relative mt-1">
            <input id="ingredient-search" type="text" wire:model.live.debounce.300ms="search" class="input"
                   placeholder="Search ingredients…" autocomplete="off" />

            @if ($results->isNotEmpty())
                <div class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-control border border-gray-200 bg-white shadow-e2">
                    @foreach ($results as $item)
                        <button type="button" wire:click="addLine(@js($item['slug']))"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50">
                            <span class="font-medium text-gray-800">{{ $item['name'] }}</span>
                            <span class="tabular-nums text-gray-600">
                                RM {{ number_format($item['price'], 2) }}@if ($item['unit'])/{{ $item['unit'] }}@endif
                            </span>
                        </button>
                    @endforeach
                </div>
            @elseif (strlen($search) >= 2)
                <p class="help mt-2">Nothing matching “{{ $search }}” yet.</p>
            @endif
        </div>

        @if ($lines)
            <div class="mt-6 overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Ingredient</th>
                            <th class="px-4 py-3 text-center w-32">Quantity</th>
                            <th class="px-4 py-3 text-right w-32">Unit price</th>
                            <th class="px-4 py-3 text-right w-28">Cost</th>
                            <th class="px-3 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                            <tr wire:key="line-{{ $line['slug'] }}">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $line['name'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <input type="number" min="0" step="0.01"
                                               wire:model.live.debounce.400ms="lines.{{ $i }}.qty"
                                               class="input w-24 text-center tabular-nums" />
                                        <span class="text-xs text-gray-600">{{ $line['unit'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    RM {{ number_format($line['price'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                    RM {{ number_format($line['price'] * $line['qty'], 2) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <button type="button" wire:click="removeLine({{ $i }})"
                                            aria-label="Remove {{ $line['name'] }}"
                                            class="icon-btn text-danger-400 hover:text-danger-600">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="portions" class="label">Portions this makes</label>
                    <input id="portions" type="number" min="1" step="1" wire:model.live="portions" class="input mt-1" />
                </div>
                <div>
                    <label for="foodcost" class="label">Target food cost</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input id="foodcost" type="number" min="1" max="99" step="1"
                               wire:model.live="targetFoodCostPct" class="input" />
                        <span class="text-sm text-gray-600">%</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="stat">
                    <p class="stat-label">Cost to make</p>
                    <p class="stat-value tabular-nums">RM {{ number_format($totals['cost'], 2) }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Per portion</p>
                    <p class="stat-value tabular-nums">RM {{ number_format($totals['per_portion'], 2) }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Sell it for</p>
                    <p class="stat-value tabular-nums text-brand-600">RM {{ number_format($totals['menu_price'], 2) }}</p>
                </div>
            </div>

            {{-- The email is asked for here and nowhere earlier: at the point
                 somebody has decided the thing is worth keeping. --}}
            <div class="mt-8 rounded-panel border border-brand-200 bg-brand-50/60 p-5">
                <h2 class="text-sm font-semibold text-gray-800">Email yourself the costing</h2>
                <p class="help mt-1">A one-page PDF with every line, the totals and the suggested price.</p>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input type="email" wire:model="email" class="input flex-1" placeholder="you@restaurant.com" />
                    <button type="button" wire:click="emailPdf"
                            wire:loading.attr="disabled" wire:target="emailPdf"
                            class="btn-primary whitespace-nowrap">
                        <span wire:loading.remove wire:target="emailPdf">Email me the PDF</span>
                        <span wire:loading wire:target="emailPdf">Sending…</span>
                    </button>
                </div>

                @if ($error)
                    <p class="error-text mt-2" role="alert">{{ $error }}</p>
                @endif
                @if ($notice)
                    <p class="mt-2 text-sm font-medium text-success-700" aria-live="polite">{{ $notice }}</p>
                @endif
            </div>
        @else
            <div class="empty-state mt-8">
                <p class="font-medium text-gray-800">Start by searching for an ingredient</p>
                <p class="help mt-1">Prices are the median paid by kitchens running on Servora.</p>
            </div>
        @endif
    </div>

    <p class="mt-6 text-center text-xs text-gray-600">
        Prices move with the market and suppliers are not identified. Costing every recipe you sell,
        automatically, is what Servora does —
        <a href="{{ route('saas.register') }}" class="text-brand-600 underline">start a free trial</a>.
    </p>
</div>
