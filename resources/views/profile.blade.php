<x-app-layout>
    <x-slot name="title">Profile</x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        <h2 class="text-lg font-semibold text-gray-700">Profile</h2>

        {{-- Switch Outlet --}}
        <div id="switch-outlet">
            @livewire('outlet-switcher')
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
