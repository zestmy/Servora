{{--
    The wall.

    Deliberately picture-led rather than a table: this is the screen somebody
    opens to show a manager or to prove a refresher was done, and a row of dates
    proves nothing at a glance. The seal, the course and the serial do.
--}}
<div class="p-4 space-y-4">

    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Learning</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-0.5">My certificates</h1>
        <p class="text-sm text-gray-600 mt-0.5">
            {{ $valid->count() }} {{ Str::plural('certificate', $valid->count()) }} in date.
        </p>
    </div>

    @forelse ($certificates as $certificate)
        @php
            $revoked = $certificate->isRevoked();
            $expired = ! $revoked && $certificate->isExpired();
        @endphp

        <div wire:key="cert-{{ $certificate->id }}"
             class="card overflow-hidden {{ $revoked || $expired ? 'opacity-75' : '' }}">
            <div class="flex items-start gap-3 p-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full
                             {{ $revoked ? 'bg-gray-100' : ($expired ? 'bg-warning-100' : 'bg-success-100') }}">
                    <x-icon name="trophy" size="h-6 w-6"
                            class="{{ $revoked ? 'text-gray-500' : ($expired ? 'text-warning-700' : 'text-success-700') }}" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900">{{ $certificate->title }}</p>
                    @if ($certificate->course)
                        <p class="text-sm text-gray-600">{{ $certificate->course->title }}</p>
                    @endif

                    <p class="mt-1 text-xs text-gray-600">
                        Earned {{ $certificate->issued_at?->format('j M Y') }}
                        @if ($certificate->expires_on)
                            · {{ $expired ? 'expired' : 'valid until' }}
                            {{ $certificate->expires_on->format('j M Y') }}
                        @endif
                    </p>

                    <p class="mt-1 font-mono text-[11px] text-gray-500">{{ $certificate->serial }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($revoked)
                            {{-- Listed rather than hidden: a certificate that
                                 quietly vanishes leaves somebody certain they
                                 earned it and unable to find out what happened. --}}
                            <span class="badge-neutral">Withdrawn</span>
                            <span class="help">Ask your manager if you think this is wrong.</span>
                        @else
                            @if ($expired)
                                <span class="badge-warning">Needs redoing</span>
                            @endif
                            <a href="{{ route('clock.staff.certificate', $certificate->id) }}"
                               class="btn-secondary btn-sm">
                                <x-icon name="download" size="h-4 w-4" class="mr-1" /> Download
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="empty-state">
            <x-icon name="trophy" size="h-8 w-8" class="text-gray-500" />
            <p class="empty-title">No certificates yet</p>
            <p class="empty-body">
                Pass a course that awards one and it lands here, ready to download.
            </p>
            <a href="{{ route('clock.staff.learn') }}" wire:navigate class="btn-primary mt-3">
                Find something to take
            </a>
        </div>
    @endforelse

    <a href="{{ route('clock.staff.learn.progress') }}" wire:navigate
       class="btn-ghost w-full justify-center">My progress</a>
</div>
