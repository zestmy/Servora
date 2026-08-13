<div>
    <x-page-header title="Roles & Access" eyebrow="Settings">
        <x-slot:actions>
            <a href="{{ route('settings.users') }}" wire:navigate class="btn-secondary">Manage users</a>
        </x-slot:actions>
    </x-page-header>

    <x-access-tabs :current="$tab === 'effective' ? 'effective' : 'roles'" />

    {{-- ------------------------------------------------------------------ Roles --}}
    @if ($tab !== 'effective')
        @if (session()->has('error'))
            <div class="alert-danger mb-4"><x-icon name="warning" class="h-5 w-5 shrink-0" /><p>{{ session('error') }}</p></div>
        @endif
        @if (session()->has('success'))
            <div class="alert-success mb-4"><x-icon name="check" class="h-5 w-5 shrink-0" /><p>{{ session('success') }}</p></div>
        @endif

        <div class="alert-info mb-4">
            <x-icon name="info" class="h-5 w-5 shrink-0" />
            <div>
                <p class="font-medium">A role is a starting point, not a cage.</p>
                <p class="help mt-0.5 text-brand-800/80">
                    It grants the abilities listed below to everyone holding it. On the Users tab you can add
                    more for one person, or untick one of the role's own abilities to take it away from them
                    alone. <span class="font-medium">System roles are shared with every company on Servora</span>,
                    so they can only be changed by a Servora administrator.
                    @if ($canEditPresets)
                        <a href="{{ route('admin.role-templates') }}" wire:navigate class="underline font-medium">Edit role templates</a>.
                    @endif
                    Roles you create belong to this company only.
                </p>
            </div>
        </div>

        <div class="toolbar mb-4">
            <input type="text" wire:model.live.debounce.200ms="search" class="input max-w-xs"
                   placeholder="Filter abilities…" aria-label="Filter abilities" />
            <span class="help">{{ count($roles) }} roles · {{ count($titles) }} abilities</span>
            <button wire:click="newRole" class="btn-primary btn-sm ml-auto">+ New role</button>
        </div>

        <div class="space-y-4">
            @foreach ($roles as $role)
                @php
                    $held = $role['abilities'];
                @endphp
                <div class="card p-5" wire:key="role-{{ $role['id'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div class="min-w-[240px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-sm font-semibold text-gray-800">{{ $role['label'] }}</h2>
                                <span class="badge-brand">{{ $role['users'] }} {{ \Illuminate\Support\Str::plural('user', $role['users']) }}</span>
                                @if ($role['is_preset'])
                                    <span class="badge-neutral" title="Shared with every company on Servora">system role</span>
                                @else
                                    <span class="badge-success" title="Created by this company, invisible to others">your role</span>
                                @endif
                            </div>
                            <p class="help mt-1 max-w-2xl">{{ $role['description'] }}</p>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="text-right">
                                <p class="stat-value text-lg">{{ count($held) }}<span class="text-gray-600 text-sm">/{{ count($titles) }}</span></p>
                                <p class="stat-label">abilities</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                @if ($role['is_preset'])
                                    <button wire:click="newRole({{ $role['id'] }})" class="btn-secondary btn-sm whitespace-nowrap"
                                            title="Create a role for this company starting from this one">Copy &amp; edit</button>
                                @else
                                    <button wire:click="editRole({{ $role['id'] }})" class="btn-secondary btn-sm">Edit</button>
                                    <button wire:click="deleteRole({{ $role['id'] }})"
                                            data-confirm-delete="Delete the role “{{ $role['label'] }}”?"
                                            class="btn-ghost btn-sm text-danger-600">Delete</button>
                                @endif
                                @if ($role['users'] > 0)
                                    <button wire:click="previewApply({{ $role['id'] }})" class="btn-ghost btn-sm whitespace-nowrap"
                                            title="Clear individually-granted abilities so this role is the only source">Apply to holders</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    @php $shown = 0; @endphp
                    <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($moduleGrid as $groupLabel => $groupModules)
                            @php
                                // Filter first so an empty group is not rendered as a bare heading.
                                $visible = [];
                                foreach ($groupModules as $key => $module) {
                                    $matches = $search === ''
                                        || str_contains(mb_strtolower($module['label']), mb_strtolower($search));
                                    $abilities = array_filter($module['abilities'], fn ($a) =>
                                        $matches || str_contains(mb_strtolower($a['title']), mb_strtolower($search)));
                                    if ($abilities) $visible[$key] = ['label' => $module['label'], 'abilities' => $abilities];
                                }
                                $shown += count($visible);
                            @endphp
                            @if ($visible)
                                <div class="break-inside-avoid">
                                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mb-1.5">{{ $groupLabel }}</p>
                                    <div class="space-y-1.5">
                                        @foreach ($visible as $moduleKey => $module)
                                            @php
                                                $names   = array_column($module['abilities'], 'name');
                                                $granted = array_values(array_intersect($names, $held));
                                                $state   = count($granted) === 0 ? 'none'
                                                         : (count($granted) === count($names) ? 'all' : 'some');
                                            @endphp
                                            <div wire:key="r{{ $role['id'] }}-m{{ $moduleKey }}">
                                                <div class="flex items-center gap-2">
                                                    {{-- Shape as well as colour: a single-ability module has no
                                                         "2/3" count to fall back on, so a green dot against a grey
                                                         one would be the only signal — unreadable to anyone who
                                                         cannot separate the two, and silent to a screen reader. --}}
                                                    @if ($state === 'all')
                                                        <x-icon name="check" class="h-4 w-4 shrink-0 text-success-600" />
                                                    @elseif ($state === 'some')
                                                        <span class="inline-block h-2 w-2 rounded-full bg-warning-500 shrink-0 mx-1"></span>
                                                    @else
                                                        <span class="text-gray-500 shrink-0 w-4 text-center leading-4" aria-hidden="true">&ndash;</span>
                                                    @endif
                                                    <span class="sr-only">{{ $state === 'all' ? 'Granted' : ($state === 'some' ? 'Partly granted' : 'Not granted') }}:</span>
                                                    <span class="text-sm {{ $state === 'none' ? 'text-gray-600' : 'text-gray-800 font-medium' }}">
                                                        {{ $module['label'] }}
                                                    </span>
                                                    @if (count($names) > 1)
                                                        <span class="help">{{ count($granted) }}/{{ count($names) }}</span>
                                                    @endif
                                                </div>
                                                @if (count($names) > 1 && $granted)
                                                    <div class="flex flex-wrap gap-1 mt-1 ml-4">
                                                        @foreach ($module['abilities'] as $ability)
                                                            @if (in_array($ability['name'], $granted, true))
                                                                <span class="badge-neutral" title="{{ $ability['help'] ?? '' }}">{{ $ability['label'] }}</span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if ($shown === 0)
                        <p class="help">No abilities match “{{ $search }}”.</p>
                    @endif

                    {{-- Inline rather than a dialog: this is a decision about THIS role, and
                         it reads better under the abilities it is about than floating over
                         them. Names are listed, not just counted — this changes real access
                         for several people at once. --}}
                    @if ($applyRoleId === $role['id'])
                        @php $affected = collect($applyPreview)->where('extras', '>', 0); @endphp
                        <div class="mt-4 rounded-surface border border-warning-200 bg-warning-50 p-4">
                            @if ($affected->isEmpty())
                                <p class="text-sm text-warning-900">
                                    Every holder of <span class="font-semibold">{{ $role['label'] }}</span> already has
                                    exactly what the role gives. Nothing to clear.
                                </p>
                            @else
                                <p class="text-sm text-warning-900">
                                    This clears abilities granted to these people individually, so
                                    <span class="font-semibold">{{ $role['label'] }}</span> becomes the only source of
                                    what they can do. It does not change the role itself, and only affects this company.
                                </p>
                                <ul class="mt-2 space-y-0.5">
                                    @foreach ($affected as $person)
                                        <li class="text-sm text-warning-900">
                                            <span class="font-medium">{{ $person['name'] }}</span>
                                            <span class="text-warning-800">— loses {{ $person['extras'] }}
                                                {{ \Illuminate\Support\Str::plural('personal ability', $person['extras']) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="flex items-center gap-2 mt-3">
                                <button wire:click="applyRoleToHolders" class="btn-primary btn-sm">
                                    {{ $affected->isEmpty() ? 'Close' : 'Apply to ' . $affected->count() . ' ' . \Illuminate\Support\Str::plural('person', $affected->count()) }}
                                </button>
                                <button wire:click="cancelApply" class="btn-ghost btn-sm">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Role editor. Only ever edits this company's own roles: a preset is one shared
             row, so "Copy & edit" clones it into a company role rather than changing it
             for every tenant on the platform. --}}
        <div x-data="{ open: false }"
             @open-role-editor.window="open = true"
             @close-role-editor.window="open = false">
            <template x-teleport="body">
                <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[100] overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                    <div class="relative min-h-full flex items-start justify-center p-4">
                        <div class="relative bg-white rounded-panel shadow-e4 w-full max-w-2xl my-8" @click.stop>
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-800">
                                    {{ $editingRoleId ? 'Edit role' : 'New role for this company' }}
                                </h3>
                                <button @click="open = false" class="icon-btn" aria-label="Close">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                                <div>
                                    <label for="roleLabel" class="label label-req">Role name</label>
                                    <input id="roleLabel" type="text" wire:model="roleLabel" maxlength="100"
                                           class="input @error('roleLabel') input-error @enderror" placeholder="e.g. Shift Supervisor" />
                                    @error('roleLabel') <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="roleDesc" class="label">Description</label>
                                    <input id="roleDesc" type="text" wire:model="roleDesc" maxlength="255"
                                           class="input" placeholder="Shown when someone picks this role" />
                                    @error('roleDesc') <p class="error-text">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <p class="label mb-2">Abilities</p>
                                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-100">
                                        @foreach ($moduleGrid as $groupLabel => $groupModules)
                                            <div class="p-3">
                                                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mb-2">{{ $groupLabel }}</p>
                                                <div class="space-y-2">
                                                    @foreach ($groupModules as $moduleKey => $module)
                                                        @if ($module['single'])
                                                            @php $a = reset($module['abilities']); @endphp
                                                            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer"
                                                                   title="{{ $a['help'] ?? '' }}">
                                                                <input type="checkbox" wire:model="roleAbilities" value="{{ $a['name'] }}"
                                                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                                <span class="text-sm text-gray-700">{{ $module['label'] }}</span>
                                                            </label>
                                                        @else
                                                            <div class="px-2">
                                                                <p class="text-xs font-medium text-gray-600 mb-1">{{ $module['label'] }}</p>
                                                                <div class="grid grid-cols-2 gap-1">
                                                                    @foreach ($module['abilities'] as $a)
                                                                        <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer"
                                                                               title="{{ $a['help'] ?? '' }}">
                                                                            <input type="checkbox" wire:model="roleAbilities" value="{{ $a['name'] }}"
                                                                                   class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                                            <span class="text-sm text-gray-700">{{ $a['label'] }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 px-5 py-4 border-t border-gray-100">
                                <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                                <button wire:click="saveRole" class="btn-primary">Save role</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    {{-- -------------------------------------------------------- Effective access --}}
    @if ($tab === 'effective')
        <div class="toolbar mb-4">
            <label for="subject" class="label mb-0">Show access for</label>
            <select id="subject" wire:model.live="userId" class="input max-w-sm">
                <option value="">Choose a person…</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                @endforeach
            </select>
        </div>

        @if (! $effective)
            <div class="empty-state">
                <x-icon name="users" class="h-8 w-8 text-gray-400" />
                <p class="text-sm font-medium text-gray-800">Pick someone to see exactly what they can reach</p>
                <p class="help max-w-md text-center">
                    Every ability is listed with where it came from — their role, or something granted to
                    them alone. This is the answer to “why can this person see payroll?”.
                </p>
            </div>
        @else
            <div class="panel p-5 mb-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $effective['user']->name }}</p>
                        <p class="help">{{ $effective['user']->email }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            @if ($effective['isSystem'])
                                <span class="badge-warning">System account — passes every check</span>
                            @elseif ($effective['roleLabel'])
                                <span class="badge-brand">{{ $effective['roleLabel'] }}</span>
                            @else
                                <span class="badge-neutral">No role — custom access</span>
                            @endif
                            @if (count($effective['added']))
                                <span class="badge-info">{{ count($effective['added']) }} granted individually</span>
                            @endif
                            @if (count($effective['removed']))
                                <span class="badge-danger">{{ count($effective['removed']) }} removed from their role</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="stat-value text-lg">{{ $effective['granted'] }}<span class="text-gray-600 text-sm">/{{ $effective['total'] }}</span></p>
                        <p class="stat-label">abilities</p>
                    </div>
                </div>
                <p class="help mt-3 pt-3 border-t border-gray-100">
                    <span class="font-medium text-gray-700">Outlets:</span> {{ $effective['outlets'] }}
                </p>
            </div>

            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($moduleGrid as $groupLabel => $groupModules)
                    <div class="break-inside-avoid">
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold mb-1.5">{{ $groupLabel }}</p>
                        <div class="card divide-y divide-gray-100">
                            @foreach ($groupModules as $moduleKey => $module)
                                <div class="px-3 py-2" wire:key="eff-{{ $moduleKey }}">
                                    {{-- A single-ability module's ability label IS the module label, so
                                         printing both gives "Ingredients / Ingredients". Those collapse to
                                         one row; only genuinely multi-ability modules get a heading. --}}
                                    @unless ($module['single'])
                                        <p class="text-xs font-medium text-gray-700 mb-1">{{ $module['label'] }}</p>
                                    @endunless
                                    <div class="space-y-1">
                                        @foreach ($module['abilities'] as $ability)
                                            @php $source = $effective['sources'][$ability['name']] ?? null; @endphp
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-sm {{ $source ? 'text-gray-800' : 'text-gray-600' }} {{ $module['single'] ? 'font-medium' : '' }}">
                                                    {{ $module['single'] ? $module['label'] : $ability['label'] }}
                                                </span>
                                                @switch($source)
                                                    @case('role')
                                                        <span class="badge-brand shrink-0" title="Comes with the {{ $effective['roleLabel'] }} role — changing their role would remove it">from role</span>
                                                        @break
                                                    @case('direct')
                                                        <span class="badge-info shrink-0" title="Ticked for this person alone, on top of their role">added for them</span>
                                                        @break
                                                    @case('system')
                                                        <span class="badge-warning shrink-0" title="System accounts pass every permission check">system</span>
                                                        @break
                                                    @case('denied')
                                                        <span class="badge-danger shrink-0" title="Their role grants this, but it was removed for this person specifically">removed</span>
                                                        @break
                                                    @case('implied')
                                                        <span class="badge-neutral shrink-0" title="Comes with what they can DO in this module — recording something means being able to see it">comes with the work</span>
                                                        @break
                                                    @default
                                                        <span class="text-xs text-gray-500 shrink-0" aria-label="not granted">—</span>
                                                @endswitch
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
