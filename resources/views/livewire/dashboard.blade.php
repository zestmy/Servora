<div>
    {{-- Announcements --}}
    <livewire:components.announcement-banner />

    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Role indicator --}}
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Dashboard</h2>
        <p class="text-xs text-gray-400 mt-0.5">{{ $roleName }}</p>
    </div>

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
