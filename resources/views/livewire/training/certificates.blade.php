<div>
    <x-training.flash />

    <x-page-header title="Certificates" eyebrow="Learning & Development"
                   subtitle="Proof your team is current — and warning when they are about to stop being." />

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-center gap-3 w-full">
            <div class="seg" role="tablist">
                @foreach ([
                    'expiring' => 'Expiring soon',
                    'valid'    => 'Valid',
                    'expired'  => 'Expired',
                    'revoked'  => 'Revoked',
                    'all'      => 'All',
                ] as $value => $label)
                    <button type="button" role="tab" wire:click="$set('filter', '{{ $value }}')"
                            aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                            class="seg-item {{ $filter === $value ? 'seg-item-on' : '' }}">
                        {{ $label }}
                        <span class="ml-1 tabular-nums text-xs text-gray-500">{{ $counts[$value] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>

            <div class="flex-1 min-w-[180px]">
                <label class="sr-only" for="cert-search">Search</label>
                <input id="cert-search" type="search" wire:model.live.debounce.300ms="search"
                       class="input" placeholder="Name, course or serial">
            </div>
        </div>
    </div>

    @if ($filter === 'expiring')
        <p class="help mb-3">Certificates lapsing in the next {{ $horizon }} days.</p>
    @endif

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3">Who</th>
                        <th class="px-4 py-3">For</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Issued</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                        <tr wire:key="cert-{{ $certificate->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $certificate->recipient_name }}</p>
                                <p class="text-xs text-gray-600">{{ $certificate->trainee?->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $certificate->title }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $certificate->serial }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ (float) $certificate->percent }}%</td>
                            <td class="px-4 py-3 text-gray-700">{{ $certificate->issued_at?->format('j M Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($certificate->isRevoked())
                                    <span class="badge-danger">Revoked</span>
                                @elseif (! $certificate->expires_on)
                                    <span class="text-gray-600">Never</span>
                                @elseif ($certificate->isExpired())
                                    <span class="badge-danger">{{ $certificate->expires_on->format('j M Y') }}</span>
                                @else
                                    <span class="{{ $certificate->expires_on->diffInDays(now()) <= 30 ? 'text-warning-700 font-medium' : 'text-gray-700' }}">
                                        {{ $certificate->expires_on->format('j M Y') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2 text-xs">
                                    @unless ($certificate->isRevoked())
                                        <a href="{{ route('training.certificates.pdf', $certificate->id) }}"
                                           class="text-brand-600 hover:underline">PDF</a>
                                    @endunless
                                    @can('training.assign')
                                        @if ($certificate->isRevoked())
                                            <button wire:click="reinstate({{ $certificate->id }})"
                                                    class="text-gray-600 hover:text-gray-900">Reinstate</button>
                                        @else
                                            <button wire:click="revoke({{ $certificate->id }})"
                                                    data-confirm-delete="Revoke {{ $certificate->serial }}? The PDF stops working."
                                                    class="text-gray-600 hover:text-danger-500">Revoke</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <x-icon name="shield" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">Nothing here</p>
                                    <p class="empty-body">
                                        Certificates are issued automatically when somebody passes a quiz that
                                        is set to award one.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $certificates->links() }}</div>
</div>
