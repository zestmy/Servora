<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tools</p>
        <h1 class="display-2 mt-2">Useful before you sign up for anything</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            Small tools that answer one question each. No account, no trial, no card.
        </p>
    </div>

    <div class="mt-10 grid gap-5 sm:grid-cols-2">
        @foreach ($tools as $tool)
            <a href="{{ route($tool['route']) }}"
               class="card group p-6 transition hover:border-brand-300 hover:shadow-e2">
                <div class="flex items-start gap-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tool['icon'] }}"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 group-hover:text-brand-700">
                            {{ $tool['name'] }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $tool['blurb'] }}</p>
                        <p class="mt-3 text-xs font-medium text-brand-600">
                            Open the tool &rarr;
                        </p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <p class="mt-10 text-center text-sm text-gray-600">
        Doing this for every dish on your menu, every time a price moves, is what
        <a href="{{ route('saas.register') }}" class="text-brand-600 underline">Servora</a> is for.
    </p>
</div>
