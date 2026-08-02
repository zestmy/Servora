{{-- System Admin / Super Admin Dashboard --}}
@php
    /**
     * Dashboard.php hands each tile a colour name (indigo, blue, sky, purple,
     * teal, …). Those were mapped straight through, so eight platform counts
     * arrived in eight hues — half of them not in the palette at all.
     *
     * Only genuine state keeps a tone. Companies, users and outlets are counts
     * and read in ink like every other figure in the product.
     */
    $valueColors = [
        'green' => 'text-success-700',
        'amber' => 'text-warning-700',
        'red'   => 'text-danger-700',
    ];
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach ($stats as $stat)
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
            <p class="text-2xl font-bold mt-1 {{ $valueColors[$stat['color']] ?? 'text-gray-900' }}">{{ number_format($stat['value']) }}</p>
            @if (! empty($stat['sub']))
                <p class="text-[11px] text-gray-600 mt-0.5">{{ $stat['sub'] }}</p>
            @endif
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Recent companies --}}
    <div class="lg:col-span-2 card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-600">Recently Created Companies</h3>
            <a href="{{ route('admin.company-health') }}" class="text-xs text-brand-600 hover:text-brand-800 font-medium">View all →</a>
        </div>
        <table class="table-surface min-w-full">
            <thead>
                <tr>
                    <th class="px-4 py-2.5 text-left">Company</th>
                    <th class="px-4 py-2.5 text-left">Plan</th>
                    <th class="px-4 py-2.5 text-center">Users</th>
                    <th class="px-4 py-2.5 text-center">Outlets</th>
                    <th class="px-4 py-2.5 text-left">Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentCompanies as $company)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-gray-800">{{ $company->name }}</p>
                            <p class="text-xs text-gray-600">{{ $company->slug }}</p>
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($company->subscription)
                                @php
                                    // 'blue' was the only off-palette tone here.
                                    // A trialing subscription is informational,
                                    // which is what badge-brand already means.
                                    $badge = match ($company->subscription->statusColor()) {
                                        'green' => 'badge-success',
                                        'blue'  => 'badge-brand',
                                        'amber' => 'badge-warning',
                                        'red'   => 'badge-danger',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <span class="text-xs text-gray-600">{{ $company->subscription->plan?->name ?? '—' }}</span>
                                <span class="ml-1 {{ $badge }}">
                                    {{ $company->subscription->statusLabel() }}
                                </span>
                            @else
                                <span class="text-xs text-gray-500">No subscription</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ $company->users_count }}</td>
                        <td class="px-4 py-2.5 text-center text-gray-600">{{ $company->outlets_count }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">{{ $company->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-600">No companies yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Quick links --}}
    <div class="card p-4">
        <h3 class="text-sm font-semibold text-gray-600 mb-3 px-1">Administration</h3>
        <div class="space-y-1">
            @foreach ($quickLinks as $link)
                <a href="{{ route($link['route']) }}"
                   class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-brand-50 group transition">
                    <div>
                        <p class="text-sm font-medium text-gray-700 group-hover:text-brand-700">{{ $link['label'] }}</p>
                        <p class="text-[11px] text-gray-600">{{ $link['desc'] }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-500 group-hover:text-brand-500 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    </div>
</div>
