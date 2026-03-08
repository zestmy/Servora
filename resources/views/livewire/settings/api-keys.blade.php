<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / API Keys</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-2xl space-y-6">

        {{-- Google Vision AI --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Google Vision AI</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Used for Z-Report OCR — extracts dept breakdown and session totals from uploaded Z-report images.</p>
                </div>
            </div>

            <div x-data="{ visible: false }">
                <x-input-label for="gv_key" value="API Key" />
                <div class="mt-1 flex items-center gap-2">
                    <div class="flex-1 relative">
                        <input id="gv_key"
                               wire:model="google_vision_key"
                               :type="visible ? 'text' : 'password'"
                               placeholder="AIzaSy…"
                               class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                               autocomplete="off" />
                        <button type="button" @click="visible = !visible"
                                class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg x-show="!visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="visible" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <button wire:click="save"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                        Save
                    </button>
                </div>
                <x-input-error :messages="$errors->get('google_vision_key')" class="mt-1" />
                <p class="mt-2 text-xs text-gray-400">
                    Get your key from
                    <span class="text-gray-500 font-medium">Google Cloud Console → APIs &amp; Services → Credentials</span>.
                    Enable the <span class="font-medium">Cloud Vision API</span> on your project.
                </p>
            </div>

            @if ($google_vision_key)
                <div class="mt-3 flex items-center gap-1.5 text-xs text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    API key is configured
                </div>
            @else
                <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    No API key set — Z-Report import will not work
                </div>
            @endif
        </div>

    </div>
</div>
