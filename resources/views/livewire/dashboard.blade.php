<div>
    {{-- Announcements --}}
    <livewire:components.announcement-banner />

    {{-- Flash.

         The key was `flash-{{ microtime(true) }}`, which produced a new value
         on every render — so Livewire tore the node down and rebuilt it each
         cycle, restarting the Alpine dismiss timer with it. Approve a PO and
         change the outlet filter within three seconds and the toast started
         its three seconds again. Keyed on the message, so it is replaced when
         the message changes and morphed when it has not. --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ md5(session('success')) }}"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-4" role="status">
            <x-icon name="check" size="h-5 w-5" stroke="2.2" class="mt-px flex-none" />
            <p class="font-medium">{{ session('success') }}</p>
        </div>
    @endif

    {{-- The page head.

         This was a hand-written `<h2 class="page-title">` with a bare select
         floated beside it. Two problems: the layout emits no h1, so the
         document had no root heading and every card title below hung off
         nothing; and the outlet select was the only control in the product
         not using .input, so it carried its own radius, no elevation and a
         one-ring focus state against .input's two.

         The x-page-header component emits a real h1 and is already used on
         eleven other screens. --}}
    {{-- The greeting is the eyebrow, not the title. "Good morning, MOHD AFFANDY BIN ZULKARNAIN" is
         a warmer thing to land on, but it does not tell a screen-reader user
         navigating by heading which page they are on — and that is the one job
         an h1 has. --}}
    <x-page-header title="Dashboard" :eyebrow="$greeting"
                   :subtitle="trim($roleName . ($roleName ? ' · ' : '') . $periodCaption)" />

    {{-- Filter strip. Lower elevation than a card on purpose — it is chrome
         for the content below, not a peer of it.

         The period control is new. Every builder opened with `$now = now()`
         and queried whereMonth/whereYear, which hardwired all seven dashboards
         to the current calendar month; there was no way to ask for last month,
         which is the first thing any of these figures makes you want to
         ask. --}}
    @if ($dashboardType !== 'system')
        <div class="toolbar mb-6">
            <div class="flex items-center gap-2">
                <label for="dashboardPeriod" class="text-xs font-medium text-gray-600 whitespace-nowrap">
                    Period
                </label>
                <select id="dashboardPeriod" wire:model.live="period" class="input w-auto py-1.5 text-sm">
                    @foreach ($periodOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($filterOutlets->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label for="dashboardOutletFilter" class="text-xs font-medium text-gray-600 whitespace-nowrap">
                        Outlet
                    </label>
                    <select id="dashboardOutletFilter" wire:model.live="outletFilter"
                            class="input w-auto py-1.5 text-sm">
                        <option value="">All outlets</option>
                        @foreach ($filterOutlets as $o)
                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <p class="ml-auto text-xs text-gray-600">
                As of {{ $generatedAt }}
            </p>
        </div>
    @endif

    {{-- Only the data region swaps. The header and the filters stay put, so
         the control the user just touched does not vanish under them. --}}
    <div wire:loading.delay wire:target="period,outletFilter">
        @include('livewire.dashboard.partials.loading')
    </div>

    <div wire:loading.remove wire:target="period,outletFilter">
        @if ($dashboardType === 'system')
            @include('livewire.dashboard.system')
        @elseif ($dashboardType === 'business')
            @include('livewire.dashboard.business')
        @elseif ($dashboardType === 'operations')
            @include('livewire.dashboard.operations')
        @elseif ($dashboardType === 'manager')
            @include('livewire.dashboard.manager')
        @elseif ($dashboardType === 'chef')
            @include('livewire.dashboard.chef')
        @elseif ($dashboardType === 'purchasing')
            @include('livewire.dashboard.purchasing')
        @elseif ($dashboardType === 'finance')
            @include('livewire.dashboard.finance')
        @endif
    </div>
</div>
