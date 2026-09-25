<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Audits" eyebrow="Outlet Audits"
                   subtitle="Every audit conducted, with its score and what is still open. Not the activity trail — that is Audit Logs under Business Intelligence.">
        <x-slot:actions>
            <a href="{{ route('audits.actions') }}" wire:navigate class="btn-secondary">
                <x-icon name="clipboard" size="h-4 w-4" />
                <span class="hidden sm:inline">Corrective actions</span>
                <span class="sm:hidden">Actions</span>
            </a>
            @canDo('audits.conduct')
                <a href="{{ route('audits.start') }}" wire:navigate class="btn-primary">+ New audit</a>
            @endcanDo
        </x-slot:actions>
    </x-page-header>

    <div class="toolbar mb-4">
        <div class="w-full">
            <x-quick-ranges :options="$quickRangeOptions" :current="$quickRange" />
        </div>
        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1 sm:min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search outlet, form or reference…" class="input" />
            </div>
            <input type="date" wire:model.live="dateFrom" class="input sm:w-40" />
            <input type="date" wire:model.live="dateTo" class="input sm:w-40" />
            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input sm:w-44">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($templates->count() > 1)
                <select wire:model.live="templateFilter" class="input sm:w-44">
                    <option value="">All forms</option>
                    @foreach ($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->code ?: $t->name }}</option>
                    @endforeach
                </select>
            @endif
            <select wire:model.live="statusFilter" class="input sm:w-40">
                <option value="">Any status</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button wire:click="resetFilters" class="btn-ghost">Reset</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[760px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-28">Date</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left">Form</th>
                        <th class="px-4 py-3 text-left w-40">Auditor</th>
                        <th class="px-4 py-3 text-right w-24">Score</th>
                        <th class="px-4 py-3 text-right w-28">Open NC</th>
                        <th class="px-4 py-3 text-left w-32">Status</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($audits as $audit)
                        @php $band = \App\Services\Audits\AuditScoreService::band($audit->score_percent !== null ? (float) $audit->score_percent : null); @endphp
                        <tr wire:key="audit-{{ $audit->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $audit->audit_date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('audits.show', $audit->id) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">
                                    {{ $audit->outlet?->name ?? '—' }}
                                </a>
                                @if ($audit->reference_number)
                                    <span class="block text-xs text-gray-600">{{ $audit->reference_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $audit->template_code ? $audit->template_code . ' · ' : '' }}{{ $audit->template_name }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $audit->auditor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold
                                {{ ['good' => 'text-success-700', 'fair' => 'text-warning-700', 'poor' => 'text-danger-700', 'none' => 'text-gray-500'][$band] }}">
                                {{ $audit->isDraft() ? '—' : ($audit->score_percent !== null ? number_format($audit->score_percent, 1) . '%' : '—') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $audit->open_findings_count ? 'text-danger-700 font-medium' : 'text-gray-600' }}">
                                {{ $audit->isDraft() ? '—' : $audit->open_findings_count }}
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'badge-neutral' => $audit->status === 'draft',
                                    'badge-info'    => $audit->status === 'submitted',
                                    'badge-warning' => $audit->status === 'acknowledged',
                                    'badge-success' => $audit->status === 'closed',
                                ])>{{ $audit->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @canDo('audits.delete')
                                    <button wire:click="delete({{ $audit->id }})" class="icon-btn icon-btn-danger" title="Delete"
                                            data-confirm-delete="Delete the {{ $audit->template_code ?: $audit->template_name }} audit of {{ $audit->outlet?->name }} on {{ $audit->audit_date->format('d M Y') }}. Its findings, photos and corrective actions go with it.">
                                        <x-icon name="trash" size="h-4 w-4" />
                                    </button>
                                @endcanDo
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12">
                                <div class="empty-state">
                                    <p class="empty-title">No audits yet</p>
                                    <p class="empty-body">
                                        Start one from an audit form. If there is no form yet, Audit Forms has a ROSE starter to install.
                                    </p>
                                    @canDo('audits.conduct')
                                        <a href="{{ route('audits.start') }}" wire:navigate class="btn-primary">+ New audit</a>
                                    @endcanDo
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($audits->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $audits->links() }}</div>
        @endif
    </div>
</div>
