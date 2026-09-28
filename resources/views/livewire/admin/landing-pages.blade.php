<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Country Pages" eyebrow="Content"
                   subtitle="The marketing home page in a local language, for the countries you choose. Prices on it follow each visitor's own currency (Billing › Currencies).">
        <x-slot:actions>
            <button type="button" wire:click="$set('showModal', true)" class="btn-primary">+ New country page</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <table class="table-surface min-w-full">
            <thead>
                <tr>
                    <th class="px-5 py-3 text-left">Language</th>
                    <th class="px-5 py-3 text-left">Address</th>
                    <th class="px-5 py-3 text-left">Countries</th>
                    <th class="px-5 py-3 text-left">Translated</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center w-48">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pages as $p)
                    @php $n = $p->translatedCount(); @endphp
                    <tr wire:key="lp-{{ $p->id }}" class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <span class="font-medium text-gray-900">{{ $p->language_name }}</span>
                            <span class="font-mono text-xs text-gray-500">{{ $p->locale }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('marketing.landing', $p->slug) }}" target="_blank" class="font-mono text-brand-700 hover:underline">/{{ $p->slug }}</a>
                        </td>
                        <td class="px-5 py-3 text-gray-700">
                            {{ implode(', ', (array) $p->countries) ?: '—' }}
                            @if ($p->auto_redirect)
                                <span class="badge-info ml-1" title="Visitors from these countries landing on / are sent here">Auto-redirect</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="meter-track h-1.5 w-24 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full {{ $n >= $total ? 'bg-success-500' : 'bg-brand-600' }}" style="width: {{ $total ? round($n / $total * 100) : 0 }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums text-gray-600">{{ $n }} / {{ $total }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="{{ $p->is_published ? 'badge-success' : 'badge-neutral' }}">{{ $p->is_published ? 'Live' : 'Draft' }}</span>
                        </td>
                        <td class="px-5 py-3 text-center text-xs">
                            <button wire:click="togglePublished({{ $p->id }})" class="mr-1 text-gray-600 hover:text-gray-900">{{ $p->is_published ? 'Unpublish' : 'Publish' }}</button>
                            <a href="{{ route('admin.landing-pages.edit', $p->id) }}" wire:navigate class="mr-1 font-medium text-brand-600 hover:text-brand-800">Translate</a>
                            <button wire:click="delete({{ $p->id }})" data-confirm-delete="Delete the {{ $p->language_name }} page and all its translations?"
                                    class="text-danger-600 hover:text-danger-700">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-600">No country pages yet. Everyone sees the English home page.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-gray-600">
        A box left blank shows the English, so a page can go live before it is fully translated.
        Unpublished pages open for system admins only, as a preview.
    </p>

    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4">
        <div class="absolute inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 my-8 w-full max-w-lg rounded-panel bg-white p-6 shadow-e4">
            <h3 class="text-lg font-semibold text-gray-900">New country page</h3>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Start from</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach (array_keys(\App\Livewire\Admin\LandingPages::PRESETS) as $name)
                    <button type="button" wire:click="usePreset(@js($name))"
                            class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 hover:border-brand-300 hover:bg-brand-50">{{ $name }}</button>
                @endforeach
            </div>

            <div class="mt-5 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Language name *</label>
                        <input type="text" wire:model="language_name" class="input mt-1" placeholder="Bahasa Indonesia">
                        <x-input-error :messages="$errors->get('language_name')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label">Language code *</label>
                        <input type="text" wire:model="locale" class="input mt-1 font-mono" placeholder="id">
                        <p class="help">For the page's lang attribute: id, th, zh-Hans…</p>
                        <x-input-error :messages="$errors->get('locale')" class="mt-1" />
                    </div>
                </div>
                <div>
                    <label class="label">Address *</label>
                    <div class="mt-1 flex items-center">
                        <span class="rounded-l-control border border-r-0 border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500">{{ url('/') }}/</span>
                        <input type="text" wire:model="slug" class="input rounded-l-none font-mono" placeholder="id">
                    </div>
                    <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Countries</label>
                    <input type="text" wire:model="countries" class="input mt-1 font-mono uppercase" placeholder="ID">
                    <p class="help">Two-letter codes. Visitors from these countries can be sent to this page automatically once it is live.</p>
                    <x-input-error :messages="$errors->get('countries')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="create" class="btn-primary">Create and translate</button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
