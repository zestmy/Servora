<div x-data="{
        running: false,
        async draft() {
            this.running = true;
            await $wire.startAi();
            while (await $wire.aiStep()) {}
            this.running = false;
        },
     }">
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-back-link :fallback="route('admin.landing-pages')" label="Country Pages" />

    <x-page-header :title="$language_name" eyebrow="Country page" subtitle="Each box is one string of the home page. Leave a box blank to show the English.">
        <x-slot:actions>
            <a href="{{ route('marketing.landing', $page->slug) }}" target="_blank" class="btn-secondary">Preview</a>
            <button type="button" wire:click="save" class="btn-primary">Save</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
        {{-- Settings + sections --}}
        <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
            <div class="card space-y-4 p-4">
                <div>
                    <label class="label">Language name</label>
                    <input type="text" wire:model="language_name" class="input mt-1">
                    <x-input-error :messages="$errors->get('language_name')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Code</label>
                        <input type="text" wire:model="locale" class="input mt-1 font-mono">
                        <x-input-error :messages="$errors->get('locale')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label">Address</label>
                        <input type="text" wire:model="slug" class="input mt-1 font-mono">
                        <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                    </div>
                </div>
                <div>
                    <label class="label">Countries</label>
                    <input type="text" wire:model="countries" class="input mt-1 font-mono uppercase">
                    <x-input-error :messages="$errors->get('countries')" class="mt-1" />
                </div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="is_published" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span><span class="font-medium text-gray-900">Published</span> — anyone can open /{{ $slug }}</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="auto_redirect" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span><span class="font-medium text-gray-900">Auto-redirect</span> — send visitors from these countries here from the home page. "View in English" always takes them back.</span>
                </label>
                <x-input-error :messages="$errors->get('auto_redirect')" class="mt-1" />
            </div>

            <div class="card p-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-sm font-semibold text-gray-900">Translated</p>
                    <p class="text-sm tabular-nums text-gray-600">{{ $done }} / {{ $total }}</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></div>
                </div>

                <button type="button" @click="draft()" :disabled="running" class="btn-secondary btn-sm mt-4 w-full justify-center">
                    <x-icon name="sparkles" size="h-4 w-4" />
                    <span x-show="! running">Draft blank boxes with AI</span>
                    <span x-show="running" x-cloak>Translating… <span class="tabular-nums">{{ count($aiQueue) }}</span> left</span>
                </button>
                <p class="help mt-2">A first draft only. Have someone who reads {{ $language_name }} check it before publishing.</p>
                @if ($aiError)
                    <p class="error-text mt-2">{{ $aiError }}</p>
                @endif
            </div>

            <nav class="card overflow-hidden" aria-label="Sections">
                @foreach ($grouped as $key => $keys)
                    @php [$d, $n] = $progress[$key]; @endphp
                    <button type="button" wire:click="$set('section', '{{ $key }}')"
                            @class([
                                'flex w-full items-center justify-between gap-2 border-b border-gray-100 px-4 py-2.5 text-left text-sm last:border-b-0',
                                'bg-brand-50 font-semibold text-brand-800' => $section === $key,
                                'text-gray-700 hover:bg-gray-50' => $section !== $key,
                            ])>
                        <span class="truncate">{{ \App\Support\Marketing\HomeCopy::SECTIONS[$key] ?? $key }}</span>
                        <span class="flex-none text-xs tabular-nums {{ $d === $n ? 'text-success-700' : 'text-gray-500' }}">{{ $d }}/{{ $n }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- Strings --}}
        <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <h2 class="text-base font-semibold text-gray-900">{{ \App\Support\Marketing\HomeCopy::SECTIONS[$section] ?? $section }}</h2>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="onlyMissing" class="rounded border-gray-300 text-brand-600"> Only blank
                    </label>
                    <button type="button" wire:click="clearSection" data-confirm-delete="Clear every translation in this section?" class="text-xs text-danger-600 hover:text-danger-700">Clear section</button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($fields as $key => $english)
                    @php $field = \App\Livewire\Admin\LandingPageForm::field($key); @endphp
                    <div wire:key="str-{{ $field }}" class="grid gap-3 py-4 md:grid-cols-2">
                        <div>
                            <p class="font-mono text-[11px] text-gray-500">{{ $key }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-gray-800">{{ $english }}</p>
                        </div>
                        <div>
                            <textarea wire:model.blur="strings.{{ $field }}" rows="{{ strlen($english) > 120 ? 4 : (strlen($english) > 50 ? 2 : 1) }}"
                                      dir="auto" lang="{{ $locale }}" placeholder="{{ $english }}"
                                      class="input text-sm"></textarea>
                            @php preg_match_all('/:[a-z]+/', $english, $ph); $val = $strings[$field] ?? ''; @endphp
                            @if ($val !== '' && collect($ph[0])->reject(fn ($p) => str_contains($val, $p))->isNotEmpty())
                                <p class="error-text mt-1">Keep {{ implode(', ', $ph[0]) }} in the translation — it is filled in with a real figure.</p>
                            @endif
                            <x-input-error :messages="$errors->get('strings.'.$field)" class="mt-1" />
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-600">Every string in this section is translated.</p>
                @endforelse
            </div>

            <div class="mt-4 flex justify-end border-t border-gray-100 pt-4">
                <button type="button" wire:click="save" class="btn-primary">Save</button>
            </div>
        </section>
    </div>
</div>
