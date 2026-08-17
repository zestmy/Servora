<div>
    {{-- Hero --}}
    <section class="bg-gradient-to-b from-brand-50/70 to-white">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <p class="page-eyebrow">Downloads</p>
            <h1 class="display-2 mt-2">Connect your outlets to Servora</h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
                Small Windows programs that run quietly on an outlet PC or POS terminal —
                printing labels and syncing sales so nobody has to.
            </p>
        </div>
    </section>

    {{-- Tools --}}
    <section class="mx-auto max-w-4xl px-4 pb-16 sm:px-6 lg:px-8">
        @if ($entries === [])
            <div class="card p-10 text-center">
                <p class="text-gray-600">Downloads are being prepared — check back shortly.</p>
            </div>
        @else
            <div class="space-y-8">
                @foreach ($entries as $entry)
                    <div class="card p-6 sm:p-8 reveal" data-reveal-index="{{ $loop->index }}">
                        <div class="flex flex-wrap items-start gap-5">
                            <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center">
                                <x-icon :name="$entry['icon']" size="h-7 w-7" />
                            </div>

                            <div class="flex-1 min-w-[16rem]">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold text-gray-900">{{ $entry['name'] }}</h2>
                                    <span class="badge-neutral">v{{ $entry['version'] }}</span>
                                    @if ($entry['stage'] !== 'Stable')
                                        <span class="badge-warning">{{ $entry['stage'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 font-medium text-gray-700">{{ $entry['tagline'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $entry['description'] }}</p>

                                <div class="mt-5 grid gap-6 sm:grid-cols-2">
                                    <div>
                                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">Before you start</h3>
                                        <ul class="space-y-1.5">
                                            @foreach ($entry['requirements'] as $requirement)
                                                <li class="flex items-start gap-2 text-sm text-gray-700">
                                                    <x-icon name="check" size="h-4 w-4" class="mt-0.5 flex-shrink-0 text-success-600" />
                                                    <span>{{ $requirement }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">Setting it up</h3>
                                        <ol class="space-y-1.5">
                                            @foreach ($entry['steps'] as $step)
                                                <li class="flex items-start gap-2 text-sm text-gray-700">
                                                    <span class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold flex items-center justify-center">{{ $loop->iteration }}</span>
                                                    <span>{{ $step }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-5">
                                    @foreach ($entry['files'] as $file)
                                        <a href="{{ $file['url'] }}" download
                                           class="{{ $loop->first ? 'btn-primary' : 'btn-secondary' }}">
                                            <x-icon name="download" size="h-4 w-4" class="mr-1.5" />
                                            Download {{ $file['label'] }}
                                            <span class="ml-1.5 {{ $loop->first ? 'text-white/70' : 'text-gray-500' }} text-xs">{{ $file['size'] }}</span>
                                        </a>
                                    @endforeach
                                    <span class="text-xs text-gray-500">Windows &middot; Updated {{ $entry['files'][0]['updated'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Pairing note --}}
        <div class="mt-10 rounded-panel border border-gray-200 bg-gray-50 p-6 text-center">
            <p class="text-sm text-gray-700">
                Every companion app pairs with your Servora account using a one-time code issued by a manager
                inside Servora — a download on its own can't reach any data.
            </p>
            <p class="mt-2 text-sm text-gray-600">
                Not using Servora yet?
                <a href="{{ route('marketing.home') }}" class="font-medium text-brand-600 hover:text-brand-700">See what it does</a>
                or
                <a href="{{ route('saas.register') }}" class="font-medium text-brand-600 hover:text-brand-700">start free</a>.
            </p>
        </div>
    </section>
</div>
