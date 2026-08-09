<div>
    <x-page-header title="Roles & Access" eyebrow="Settings">
        <x-slot:actions>
            <a href="{{ route('settings.users') }}" wire:navigate class="btn-secondary">Manage users</a>
        </x-slot:actions>
    </x-page-header>

    <x-access-tabs :current="$tab === 'effective' ? 'effective' : 'roles'" />

    {{-- ------------------------------------------------------------------ Roles --}}
    @if ($tab !== 'effective')
        <div class="alert-info mb-4">
            <x-icon name="info" class="h-5 w-5 shrink-0" />
            <div>
                <p class="font-medium">Roles are shared across every company on Servora.</p>
                <p class="help mt-0.5 text-brand-800/80">
                    A role is a starting point: it guarantees the abilities ticked below, and you can grant
                    extra ones per person on the Users tab. Because one definition is shared by all companies,
                    only a Servora system administrator can change what a role includes.
                    @if ($canEditRoles)
                        <a href="{{ route('admin.role-templates') }}" wire:navigate class="underline font-medium">Edit role templates</a>.
                    @endif
                </p>
            </div>
        </div>

        <div class="toolbar mb-4">
            <input type="text" wire:model.live.debounce.200ms="search" class="input max-w-xs"
                   placeholder="Filter abilities…" aria-label="Filter abilities" />
            <span class="help">{{ count($roles) }} roles · {{ count($titles) }} abilities</span>
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
                            </div>
                            <p class="help mt-1 max-w-2xl">{{ $role['description'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="stat-value text-lg">{{ count($held) }}<span class="text-gray-600 text-sm">/{{ count($titles) }}</span></p>
                            <p class="stat-label">abilities</p>
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
                </div>
            @endforeach
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
