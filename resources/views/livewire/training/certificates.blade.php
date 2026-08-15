<div>
    <x-training.flash />

    <x-page-header title="Certificates" eyebrow="Learning & Development"
                   subtitle="Proof your team is current — and warning when they are about to stop being.">
        <x-slot:actions>
            @can('training.manage')
                <button type="button" wire:click="openSignatory" class="btn-secondary">
                    <x-icon name="cog" size="h-4 w-4" /> Signatory
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

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
                                <p class="text-xs text-gray-600">{{ $certificate->employee?->email }}</p>
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

    {{-- ── The signatory ──

         One signatory for the whole company, printed on every certificate the
         moment it is saved — the PDF is rendered live from the row, so there
         is no reissue step and nothing to backfill. --}}
    @if ($showSignatory)
        {{-- Teleported to body like every training modal: layouts.app wraps
             pages in `.page-enter`, whose animation fill mode leaves a
             transform behind, and a transformed ancestor becomes the
             containing block for position:fixed — the overlay clips to the
             content column instead of covering the window. See the note on
             the assignments modal. --}}
        @teleport('body')
        <div class="fixed inset-0 z-overlay overflow-y-auto bg-gray-900/50 p-4
                    flex items-start justify-center sm:items-center"
             wire:key="signatory-modal">
            <div class="my-auto w-full max-w-lg">
                <div class="panel p-6 space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Certificate signatory</h2>
                        <p class="help">
                            Printed in the signature block of every certificate, including ones
                            already earned — they are rendered fresh on each download. Leave it
                            empty to close certificates with the company name alone.
                        </p>
                    </div>

                    <div>
                        <label class="label" for="sig-name">Name</label>
                        <input id="sig-name" type="text" wire:model="signatoryName" class="input"
                               placeholder="e.g. Sarah Tan">
                        @error('signatoryName') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="sig-title">Designation</label>
                        <input id="sig-title" type="text" wire:model="signatoryTitle" class="input"
                               placeholder="e.g. Head of Training">
                        @error('signatoryTitle') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="sig-company">Company name</label>
                        <input id="sig-company" type="text" wire:model="signatoryCompany" class="input"
                               placeholder="As it should appear under the signature">
                        <p class="help">May differ from the operating company — an academy arm or HQ entity.</p>
                        @error('signatoryCompany') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <p class="label">Signature image</p>

                        @php
                            /* The extension check keeps temporaryUrl() from throwing
                               on a not-yet-converted HEIC — a throw here is a dead
                               modal, not a missing thumbnail. */
                            $signaturePreviewable = $signatureFile && is_object($signatureFile) && in_array(
                                strtolower($signatureFile->getClientOriginalExtension()),
                                ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                                true,
                            );
                        @endphp

                        @if ($signaturePreviewable || $signaturePath)
                            <div class="mb-2 rounded-surface border border-gray-200 bg-gray-50 p-3">
                                <img class="max-h-16"
                                     src="{{ $signaturePreviewable ? $signatureFile->temporaryUrl() : Storage::disk('public')->url($signaturePath) }}"
                                     alt="Signature">
                                @if ($signaturePath && ! $signatureFile)
                                    <button type="button" wire:click="removeSignature"
                                            data-confirm-delete="Remove the signature image? Certificates print without a pen stroke until a new one is uploaded."
                                            class="mt-2 text-xs text-gray-600 hover:text-danger-600">Remove</button>
                                @endif
                            </div>
                        @endif

                        <label class="sr-only" for="sig-file">Upload a signature image</label>
                        <input id="sig-file" type="file" wire:model="signatureFile" accept="image/*" class="input">
                        <p class="help mt-1" wire:loading wire:target="signatureFile">Uploading…</p>
                        <p class="help">
                            A dark pen stroke on a white or transparent background prints best.
                            PNG with transparency sits cleanly on the certificate's paper.
                        </p>
                        @error('signatureFile') <p class="error-text">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showSignatory', false)" class="btn-ghost">Cancel</button>
                        <button type="button" wire:click="saveSignatory" class="btn-primary">Save signatory</button>
                    </div>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
