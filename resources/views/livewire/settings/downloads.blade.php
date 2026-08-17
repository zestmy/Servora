<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <x-icon name="chevron-left" class="w-5 h-5" />
            </a>
            <div>
                <p class="page-eyebrow">Settings</p>
                <h1 class="page-title mt-1">Files &amp; Downloads</h1>
            </div>
        </div>
    </div>

    <div class="panel p-4 mb-6">
        <p class="text-sm text-gray-700">
            Small programs that run on your outlet PCs and POS terminals and connect them to Servora.
            Download the zip on the machine itself, or on any PC and carry it over on a USB stick.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            Each tool pairs with Servora using a code a manager issues from its own screen — the download
            alone can't reach your data.
        </p>
    </div>

    <div class="space-y-6">
        @foreach ($entries as $entry)
            <div class="card p-6">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center">
                        <x-icon :name="$entry['icon']" size="h-6 w-6" />
                    </div>

                    <div class="flex-1 min-w-[16rem]">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-gray-800">{{ $entry['name'] }}</h2>
                            <span class="badge-neutral">v{{ $entry['version'] }}</span>
                            @if ($entry['stage'] !== 'Stable')
                                <span class="badge-warning">{{ $entry['stage'] }}</span>
                            @else
                                <span class="badge-success">{{ $entry['stage'] }}</span>
                            @endif
                        </div>
                        <p class="text-sm font-medium text-gray-700 mt-1">{{ $entry['tagline'] }}</p>
                        <p class="text-sm text-gray-600 mt-2 max-w-3xl">{{ $entry['description'] }}</p>

                        <div class="grid gap-6 sm:grid-cols-2 mt-5">
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
                                            <span class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold flex items-center justify-center">{{ $loop->iteration }}</span>
                                            <span>{{ $step }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-gray-100">
                            @if ($entry['files'] !== [])
                                <div class="flex flex-wrap items-center gap-3">
                                    @foreach ($entry['files'] as $file)
                                        <a href="{{ $file['url'] }}" download
                                           class="{{ $loop->first ? 'btn-primary' : 'btn-secondary' }}">
                                            <x-icon name="download" size="h-4 w-4" class="mr-1.5" />
                                            Download {{ $file['label'] }}
                                            <span class="ml-1.5 {{ $loop->first ? 'text-white/70' : 'text-gray-500' }} text-xs">{{ $file['size'] }}</span>
                                        </a>
                                    @endforeach
                                    <span class="text-xs text-gray-500">Updated {{ $entry['files'][0]['updated'] }}</span>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">
                                    No installer is hosted on this server yet — ask your administrator to publish
                                    the v{{ $entry['version'] }} zip.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
