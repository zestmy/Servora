@props([
    // 'outlet' | 'kitchen' — decides the accent and which menu is drawn.
    'workspace' => 'outlet',

    // Already filtered to what this user may open.
    'groups'    => [],

    // Platform administration, appended below a rule. Empty for everyone
    // except system roles.
    'adminGroups' => [],

    // Where the collapsed-state and open-group preferences are kept. The two
    // workspaces keep separate group state — they have different groups, and
    // remembering "Production" open while standing in an outlet means nothing.
    'stateKey'  => 'nav',

    'logo'      => '/images/servora-logo-white.png',
    'logoDark'  => null,
])

@php
    use Illuminate\Support\Str;

    /**
     * The navigation panel, for both workspaces.
     *
     * There were two of these — layouts/app.blade.php and
     * layouts/kitchen.blade.php — each hand-rolling group rendering, active
     * detection, collapse behaviour and its own localStorage keys. They had
     * already drifted in four visible ways: the kitchen had a collapsed icon
     * rail and the outlet did not, the hover colour on a group header differed,
     * the active pill was a different hue, and the kitchen's permission filter
     * understood only `permission` while the outlet's understood four more keys.
     *
     * The worst of those was the collapsed rail. In the outlet sidebar every
     * group sat inside `x-show="sidebarExpanded"`, so collapsing to the 64px
     * rail left exactly one link on screen — Dashboard — and the only way to
     * reach anything else was to expand again. The kitchen had solved this
     * with a per-group icon button; the outlet never got it. One component
     * now, so it cannot be solved in one place and not the other.
     *
     * What each element resolves to per theme lives in the tokens on .app-nav
     * in app.css. Nothing here names a grey.
     */
    $isActive = function (array $item): bool {
        $route = $item['route'] ?? null;
        if (! $route) {
            return false;
        }

        $active = request()->routeIs($route) || request()->routeIs($route . '.*');

        // The reports hub owns every reports.* screen; without this, opening a
        // report leaves the sidebar with nothing highlighted.
        if ($route === 'reports.hub') {
            $active = $active || request()->routeIs('reports.*');
        }

        if (! $active) {
            return false;
        }

        if (! empty($item['query'])) {
            // Two items can share a route and differ only by query — Recipes
            // and Prep Items, Production Orders and Inventory. Match the query
            // or they both light up.
            parse_str($item['query'], $qp);
            return collect($qp)->every(fn ($v, $k) => request()->query($k) === $v);
        }

        // No query of its own: another item owns the tabbed variant.
        return ! request()->has('tab');
    };

    $groupIsActive = function (array $group) use ($isActive): bool {
        foreach ($group['items'] as $item) {
            if ($isActive($item)) {
                return true;
            }
        }
        return false;
    };

    $href = fn (array $item) => route($item['route']) . (! empty($item['query']) ? '?' . $item['query'] : '');

    $allGroups = array_merge($groups, $adminGroups);

    // Which group to open on arrival. Falls back to whatever was last open.
    $openGroup = '';
    foreach ($allGroups as $group) {
        if ($group['label'] && $groupIsActive($group)) {
            $openGroup = Str::slug($group['label']);
            break;
        }
    }
@endphp

<aside x-data="{
           activeGroup: @js($openGroup) || localStorage.getItem('{{ $stateKey }}_group') || '',
           toggleGroup(key) {
               this.activeGroup = this.activeGroup === key ? '' : key;
               localStorage.setItem('{{ $stateKey }}_group', this.activeGroup);
           },
           openGroupExpanded(key) {
               if (! this.sidebarOpen) this.toggleSidebar();
               this.activeGroup = key;
               localStorage.setItem('{{ $stateKey }}_group', key);
           },
       }"
       :class="{
           '-translate-x-full md:translate-x-0': !mobileNavOpen,
           'translate-x-0': mobileNavOpen,
           'md:w-16': !sidebarOpen,
           'md:w-64': sidebarOpen,
       }"
       @click="if ($event.target.closest && $event.target.closest('a')) mobileNavOpen = false"
       @keydown.escape.window="mobileNavOpen = false"
       :data-nav-theme="navTheme"
       data-workspace="{{ $workspace }}"
       class="app-nav fixed inset-y-0 left-0 z-overlay flex w-64 flex-shrink-0 transform flex-col
              overflow-hidden transition-all duration-300 ease-in-out
              md:relative md:inset-auto md:z-auto">

    {{-- Logo + collapse toggle --}}
    <div class="nav-header flex h-16 flex-shrink-0 items-center gap-2 border-b px-3">
        <div x-show="sidebarExpanded"
             x-transition:enter="transition-opacity duration-200 delay-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity duration-75"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="flex-1 overflow-hidden whitespace-nowrap">
            {{-- The white logo is unreadable on a white panel. The dark mark is
                 swapped in for the light theme rather than the strip being
                 kept grey to hide the problem, which is what the override
                 sheet did. --}}
            <img src="{{ $logo }}" alt="Servora" class="h-11" x-show="navTheme === 'dark'">
            <img src="{{ $logoDark ?? $logo }}" alt="Servora" class="h-11" x-show="navTheme === 'light'" x-cloak>
        </div>

        <button @click="toggleSidebar()"
                :class="sidebarOpen ? '' : 'mx-auto'"
                :aria-expanded="sidebarOpen.toString()"
                class="nav-icon-btn hidden md:flex"
                aria-controls="{{ $stateKey }}-primary">
            <span class="sr-only" x-text="sidebarOpen ? 'Collapse navigation' : 'Expand navigation'"></span>
            <x-icon name="chevrons-left" size="h-5 w-5" stroke="2" x-show="sidebarOpen" />
            <x-icon name="bars" size="h-5 w-5" stroke="2" x-show="!sidebarOpen" />
        </button>
    </div>

    {{-- Anything the workspace wants above the menu — the outlet's Scan
         Invoices and Zeoniq CTAs live here. --}}
    @isset($top)
        <div class="flex-shrink-0 space-y-1.5 pt-3" :class="sidebarExpanded ? 'px-3' : 'px-2'">
            {{ $top }}
        </div>
    @endisset

    <nav id="{{ $stateKey }}-primary"
         aria-label="{{ $workspace === 'kitchen' ? 'Central Kitchen' : 'Main' }} navigation"
         class="nav-scroll flex-1 space-y-1 overflow-y-auto py-4"
         :class="sidebarExpanded ? 'px-3' : 'px-2'">

        @foreach ($allGroups as $group)
            @php $slug = $group['label'] ? Str::slug($group['label']) : null; @endphp

            @if (! $group['label'])
                {{-- Ungrouped top-level entries (Dashboard). --}}
                @foreach ($group['items'] as $item)
                    @php $on = $isActive($item); @endphp
                    <a href="{{ $href($item) }}"
                       @if ($on) aria-current="page" @endif
                       class="nav-item {{ $on ? 'nav-item-on' : '' }}"
                       :class="sidebarExpanded ? '' : 'justify-center px-0 h-11'">
                        @if (! empty($item['icon']))
                            <x-icon :name="$item['icon']" size="h-5 w-5" stroke="1.8" class="flex-shrink-0" />
                        @endif
                        {{-- x-show sets display:none, which takes the label out
                             of the accessibility tree too — so the collapsed
                             rail needs a name that survives. A title attribute
                             was doing this job and a touch user never sees one. --}}
                        <span x-show="sidebarExpanded" class="whitespace-nowrap">{{ $item['label'] }}</span>
                        <span x-show="!sidebarExpanded" class="sr-only">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @else
                @php $groupOn = $groupIsActive($group); @endphp

                {{-- Collapsed rail: one button per group. Expands the sidebar
                     and opens that group, so the rail is a real way to
                     navigate rather than a decorative stripe. --}}
                <div x-show="!sidebarExpanded" class="flex justify-center">
                    <button type="button"
                            @click="openGroupExpanded('{{ $slug }}')"
                            class="nav-rail-btn {{ $groupOn ? 'nav-rail-btn-on' : '' }}">
                        <span class="sr-only">{{ $group['label'] }}</span>
                        @if (! empty($group['icon']))
                            <x-icon :name="$group['icon']" size="h-5 w-5" stroke="1.8" />
                        @endif
                    </button>
                </div>

                {{-- Expanded: header + collapsible list. --}}
                <div x-show="sidebarExpanded" class="mt-2">
                    <button type="button"
                            @click="toggleGroup('{{ $slug }}')"
                            :aria-expanded="(activeGroup === '{{ $slug }}').toString()"
                            aria-controls="{{ $stateKey }}-group-{{ $slug }}"
                            class="nav-section">
                        <span class="flex items-center gap-2">
                            @if (! empty($group['icon']))
                                <x-icon :name="$group['icon']" size="h-3.5 w-3.5" stroke="1.8" class="flex-shrink-0" />
                            @endif
                            <span>{{ $group['label'] }}</span>
                        </span>
                        <x-icon name="chevron-down" size="h-3 w-3" stroke="2"
                                class="transition-transform"
                                ::class="activeGroup === '{{ $slug }}' && 'rotate-180'" />
                    </button>

                    <div id="{{ $stateKey }}-group-{{ $slug }}"
                         x-show="activeGroup === '{{ $slug }}'"
                         x-collapse>
                        @php $lastSection = null; @endphp
                        @foreach ($group['items'] as $item)
                            {{-- A group may sub-divide with a 'section' key. The
                                 caption is emitted at the first VISIBLE item of
                                 each section, so a section whose items are all
                                 hidden by permission leaves no caption behind. --}}
                            @if (! empty($item['section']) && $item['section'] !== $lastSection)
                                @php $lastSection = $item['section']; @endphp
                                <p class="nav-caption">{{ $item['section'] }}</p>
                            @endif

                            @php $on = $isActive($item); @endphp
                            <a href="{{ $href($item) }}"
                               @if ($on) aria-current="page" @endif
                               class="nav-sub {{ $on ? 'nav-sub-on' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Company / outlet / user — supplied per workspace, since the two differ. --}}
    @isset($bottom)
        <div class="nav-divider flex-shrink-0 border-t">
            {{ $bottom }}
        </div>
    @endisset
</aside>
