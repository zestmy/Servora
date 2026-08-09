@props(['current' => 'users'])

{{-- Settings > Roles & Access spans two routes: Settings\Users owns creating and editing
     people, Settings\RolesAccess owns the read-and-explain tabs. This bar is what makes
     them read as one screen. wire:navigate keeps the switch instant, so the split is not
     something a user has to notice. --}}
@php
    $tabs = [
        ['key' => 'users',     'label' => 'Users',            'href' => route('settings.users')],
        ['key' => 'roles',     'label' => 'Roles',            'href' => route('settings.roles-access', ['tab' => 'roles'])],
        ['key' => 'effective', 'label' => 'Effective access', 'href' => route('settings.roles-access', ['tab' => 'effective'])],
    ];
@endphp

<div class="seg mb-4" role="tablist">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}" wire:navigate
           role="tab"
           aria-selected="{{ $current === $tab['key'] ? 'true' : 'false' }}"
           class="seg-item {{ $current === $tab['key'] ? 'seg-item-on' : '' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
