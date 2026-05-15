<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Duty Roster</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Roster Settings</h2>
        </div>
        <div>
            <a href="{{ route('hr.duty-roster') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                &larr; Back to Roster
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl">
        <form wire:submit="save" class="space-y-5">
            {{-- Outlet Selection --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                    <option value="">Select outlet...</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
                @error('outletId') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
            </div>

            @if ($outletId)
                {{-- Normal Hours --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Normal Working Hours (per day)
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" wire:model="normal_hours" step="0.5" min="1" max="24"
                               class="w-32 text-sm rounded-lg border-gray-300 shadow-sm" />
                        <span class="text-sm text-gray-500">hours</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Hours worked beyond this will count as overtime (OT).
                        <br>Example: If set to 8, an employee working 9 hours will have 8h regular + 1h OT.
                    </p>
                    @error('normal_hours') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Rest Duration --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Default Rest/Break Duration
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" wire:model="rest_duration" min="0" max="480"
                               class="w-32 text-sm rounded-lg border-gray-300 shadow-sm" />
                        <span class="text-sm text-gray-500">minutes</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Default break time deducted from shift hours.
                        <br>Example: 60 minutes = 1 hour break.
                    </p>
                    @error('rest_duration') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Week Start Day --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Week Start Day</label>
                    <select wire:model="week_start_day" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                        <option value="monday">Monday</option>
                        <option value="tuesday">Tuesday</option>
                        <option value="wednesday">Wednesday</option>
                        <option value="thursday">Thursday</option>
                        <option value="friday">Friday</option>
                        <option value="saturday">Saturday</option>
                        <option value="sunday">Sunday</option>
                    </select>
                    @error('week_start_day') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Save Button --}}
                <div class="pt-4 border-t">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Settings
                    </button>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    Please select an outlet to configure roster settings.
                </div>
            @endif
        </form>
    </div>

    {{-- Info Box --}}
    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg max-w-xl">
        <h3 class="text-sm font-medium text-blue-800 mb-2">How Regular Hours & OT are Calculated</h3>
        <ul class="text-sm text-blue-700 space-y-1">
            <li><strong>Hours Worked</strong> = Shift End - Shift Start - Rest Duration</li>
            <li><strong>Regular Hours</strong> = min(Hours Worked, Normal Hours)</li>
            <li><strong>OT Hours</strong> = Hours Worked - Normal Hours (if positive)</li>
        </ul>
        <p class="text-xs text-blue-600 mt-3">
            You can also override Normal Hours per individual entry in the roster edit modal.
        </p>
    </div>
</div>
