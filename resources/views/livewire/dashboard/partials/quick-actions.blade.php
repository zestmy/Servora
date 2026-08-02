@props(['actions', 'title' => 'Quick actions'])

{{--
    The shortcut list that opened four of the role dashboards.

    It was written out four times with emoji glyphs (🛒 💰 📦 📋 🗑️ 🚚 📥).
    Emoji are a different typeface on every OS, cannot take the icon set's
    stroke weight, cannot be recoloured, and are announced by screen readers
    with names like "shopping trolley" that have nothing to do with raising a
    purchase order. They are icons now, and there is one copy of this markup.

    The hover accent was also inconsistent: most rows went brand, but "Receive
    Goods" went success-green and "Review POs" went warning-amber, which read
    as though those rows were in a state rather than simply being links.

    Usage:

        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'purchasing.orders.create', 'icon' => 'cart', 'label' => 'New purchase order'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon' => 'inbox', 'label' => 'Receive goods', 'count' => $awaiting],
        ]])
--}}

<h2 class="mb-4 text-sm font-semibold text-gray-900">{{ $title }}</h2>

<ul class="space-y-2">
    @foreach ($actions as $action)
        <li>
            <a href="{{ route($action['route'], $action['params'] ?? []) }}"
               class="group flex items-center gap-3 rounded-control border border-gray-200 p-3
                      text-sm transition-colors hover:border-brand-300 hover:bg-brand-50">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-control
                             bg-gray-100 text-gray-600 transition-colors
                             group-hover:bg-brand-100 group-hover:text-brand-700">
                    <x-icon :name="$action['icon']" size="h-4 w-4" stroke="1.8" />
                </span>

                <span class="font-medium text-gray-800">{{ $action['label'] }}</span>

                @if (! empty($action['count']))
                    {{-- A count is a real state, so it keeps its tone. --}}
                    <span class="badge-warning ml-auto tabular-nums">
                        {{ $action['count'] }}
                        <span class="sr-only">waiting</span>
                    </span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
