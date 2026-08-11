{{-- System Admin / Super Admin Dashboard — the platform view.

     Two things were wrong here beyond the tiles' styling.

     The tiles were hand-rolled as `bg-white rounded-xl shadow-sm p-4 border
     border-gray-100` — the only ones in the product not going through the
     shared partial, and off both the shape lock (rounded-xl, not
     rounded-surface) and the elevation scale (shadow-sm, not shadow-e1).

     And eight platform counts with no growth rate between them answered "how
     big is the platform", which is not a daily question. The four figures that
     actually move lead now, with deltas; the static ones are a secondary
     group below. --}}

@include('livewire.dashboard.partials.alerts')

@include('livewire.dashboard.partials.stat-cards', ['stats' => $stats])

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- At-risk accounts.

         This replaces "Recently Created Companies". A platform admin's daily
         question is who is about to churn, not who signed up — signups are a
         weekly review and the count is already on a tile above. The
         thresholds match admin.company-health, so the dashboard and that
         screen cannot classify the same company differently. --}}
    <div class="card overflow-hidden lg:col-span-2">
        <div class="px-5 pt-5">
            <x-card-title :action="'Company health'" :action-href="route('admin.company-health')">
                Accounts going quiet
            </x-card-title>
        </div>

        @if ($atRiskCompanies->isNotEmpty())
            <div class="table-scroll border-t border-gray-100">
                <table class="table-surface">
                    <caption class="sr-only">
                        Companies with no recent user activity, least recently active first
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Company</th>
                            <th scope="col">Plan</th>
                            <th scope="col" class="text-center">Users</th>
                            <th scope="col" class="text-right">Last active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($atRiskCompanies as $company)
                            <tr>
                                <th scope="row" class="px-4 py-2.5 text-left">
                                    <p class="font-medium text-gray-900">{{ $company->name }}</p>
                                    <p class="text-xs font-normal text-gray-600">{{ $company->slug }}</p>
                                </th>
                                <td class="px-4 py-2.5">
                                    @if ($company->subscription)
                                        @php
                                            // 'blue' was the only off-palette tone
                                            // here. A trialing subscription is
                                            // informational, which is what
                                            // badge-brand already means.
                                            $badge = match ($company->subscription->statusColor()) {
                                                'green' => 'badge-success',
                                                'blue'  => 'badge-brand',
                                                'amber' => 'badge-warning',
                                                'red'   => 'badge-danger',
                                                default => 'badge-neutral',
                                            };
                                        @endphp
                                        <span class="text-xs text-gray-600">{{ $company->subscription->plan?->name ?? '—' }}</span>
                                        <span class="ml-1 {{ $badge }}">{{ $company->subscription->statusLabel() }}</span>
                                    @else
                                        <span class="text-xs text-gray-600">No subscription</span>
                                    @endif
                                </td>
                                <td class="num px-4 py-2.5 text-center text-gray-700">{{ $company->users_count }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($company->days_quiet === null)
                                        <span class="badge-neutral">Never</span>
                                    @else
                                        {{-- The word is in the badge, so the tone
                                             is reinforcing it rather than being
                                             the only thing that says it. --}}
                                        <span class="{{ $company->health === 'inactive' ? 'badge-danger' : 'badge-warning' }} tabular-nums">
                                            {{ $company->days_quiet }}d ago
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-5 pb-5">
                <div class="empty-state">
                    <x-icon name="check" size="h-8 w-8" stroke="1.8" class="text-success-600" />
                    <p class="empty-title">Every active company has been in this week</p>
                    <p class="empty-body">Accounts with no user activity for over a week will surface here.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Quick links --}}
    <div class="card p-4">
        <div class="px-1">
            <x-card-title>Administration</x-card-title>
        </div>

        <div class="space-y-1">
            @foreach ($quickLinks as $link)
                <a href="{{ route($link['route']) }}"
                   class="group flex items-center justify-between gap-3 rounded-control px-3 py-2 transition-colors hover:bg-brand-50">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-800 group-hover:text-brand-700">{{ $link['label'] }}</span>
                        <span class="block text-[11px] text-gray-600">{{ $link['desc'] }}</span>
                    </span>
                    {{-- Was a raw inline <svg> chevron — the drift the icon
                         component exists to stop. --}}
                    <x-icon name="arrow-right" size="h-4 w-4" stroke="2"
                            class="flex-none text-gray-500 transition-colors group-hover:text-brand-600" />
                </a>
            @endforeach
        </div>
    </div>
</div>

{{-- The counts that do not move day to day. Real, but not news — so they sit
     below the things that are. --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
    @foreach ($secondaryStats as $stat)
        <div class="card stat p-4">
            <span class="stat-label">{{ $stat['label'] }}</span>
            <span class="stat-value text-xl">{{ $stat['value'] }}</span>
            @if (! empty($stat['sub']))
                <span class="stat-meta">{{ $stat['sub'] }}</span>
            @endif
        </div>
    @endforeach
</div>
