<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <h1 class="text-lg font-bold text-gray-800 mb-1">Role Templates</h1>
    <p class="text-xs text-gray-400 mb-2">
        The access template behind each role: its name, description and the modules it guarantees.
        These templates drive the Access Level selector and Role Guide in Settings &gt; Users.
    </p>
    <div class="mb-6 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
        Role definitions are global — saving a template immediately changes access for <span class="font-semibold">every user holding that role, in every company</span>.
        Capabilities (approvals, GRN, invoices…) are per-user suggestions and are not part of the template.
    </div>

    <div class="space-y-4">
        @foreach ($roles as $role)
            @php $e = $edit[$role->id] ?? null; @endphp
            @if ($e)
                <div wire:key="role-{{ $role->id }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div class="flex-1 min-w-[260px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <input type="text" wire:model="edit.{{ $role->id }}.display_name" maxlength="100"
                                       class="text-sm font-semibold rounded-lg border-gray-300 w-64" />
                                <span class="text-[10px] uppercase tracking-wider text-gray-400" title="Stable internal key used by the system — not editable">key: {{ $role->name }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-100 text-indigo-700">
                                    {{ $userCounts[$role->id] ?? 0 }} {{ \Illuminate\Support\Str::plural('user', $userCounts[$role->id] ?? 0) }}
                                </span>
                            </div>
                            @error("edit.{$role->id}.display_name") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            <input type="text" wire:model="edit.{{ $role->id }}.description" maxlength="255"
                                   placeholder="Short description shown in the Access Level selector and Role Guide"
                                   class="mt-2 w-full text-xs rounded-lg border-gray-300 text-gray-600" />
                        </div>
                        <button wire:click="save({{ $role->id }})"
                                wire:confirm="Save this template? Access changes apply immediately to every user holding this role, in all companies."
                                class="px-4 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                            Save Template
                        </button>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1.5">
                        @foreach ($modules as $perm => $label)
                            <label wire:key="rp-{{ $role->id }}-{{ $perm }}"
                                   class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" wire:model="edit.{{ $role->id }}.perms" value="{{ $perm }}"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <p class="text-[11px] text-gray-400 mt-4">
        System roles (Super Admin, System Admin) are platform-level and cannot be edited here.
        Renaming changes the display name everywhere; the internal key stays stable so existing assignments and system checks are unaffected.
    </p>
</div>
