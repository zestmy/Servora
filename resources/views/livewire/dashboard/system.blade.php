{{-- System Admin / Super Admin Dashboard --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach ($stats as $stat)
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                    @if ($stat['icon'] === 'building')
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    @elseif ($stat['icon'] === 'users')
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    @elseif ($stat['icon'] === 'check')
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @elseif ($stat['icon'] === 'location')
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    @endif
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stat['value'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h3 class="text-sm font-semibold text-gray-600 mb-4">Quick Actions</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('settings.users') }}" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition">
            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <div>
                <p class="font-medium text-gray-800 text-sm">Manage Users</p>
                <p class="text-xs text-gray-500">Create and manage user accounts</p>
            </div>
        </a>
        <a href="{{ route('settings.index') }}" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition">
            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <div>
                <p class="font-medium text-gray-800 text-sm">System Settings</p>
                <p class="text-xs text-gray-500">API keys and system configuration</p>
            </div>
        </a>
    </div>
</div>
