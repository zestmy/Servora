<?php

namespace App\Livewire\Admin;

use App\Jobs\TranslateLandingPage;
use App\Models\LandingPage;
use App\Support\Marketing\HomeCopy;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * One country landing page: its settings, and every string of the home page
 * in its language beside the English (HomeCopy). A blank box shows the
 * English on the live page, so a page can go out half translated without a
 * hole in it — the counter says how far along it is.
 *
 * "Draft with AI" runs on the queue (TranslateLandingPage), a small batch
 * per job, and this screen polls for progress: a browser-driven loop broke
 * on production, where one batch outlived the 60-second request limit and
 * the screen sat on "Translating…" with nothing to say why. Results land in
 * the blank boxes only, and nothing is stored until Save.
 */
class LandingPageForm extends Component
{
    public LandingPage $page;

    public string $language_name = '';
    public string $locale = '';
    public string $slug = '';
    public string $countries = '';
    public bool $is_published = false;
    public bool $auto_redirect = false;

    /**
     * Translations by FORM key: the copy key with its dot as `__`
     * (hero.title → hero__title). Livewire reads a dot in a wire:model path
     * as nesting, so the copy keys cannot be bound as they are.
     */
    public array $strings = [];

    public string $section = 'hero';
    public bool $onlyMissing = false;

    /** An AI draft is in progress on the queue; the view polls pollAi(). */
    public bool $aiRunning = false;
    public int $aiDone = 0;
    public int $aiTotal = 0;
    public ?string $aiError = null;

    public function mount(int $id): void
    {
        $this->page = LandingPage::findOrFail($id);
        $this->language_name    = $this->page->language_name;
        $this->locale           = $this->page->locale;
        $this->slug             = $this->page->slug;
        $this->countries        = implode(', ', (array) $this->page->countries);
        $this->is_published     = $this->page->is_published;
        $this->auto_redirect    = $this->page->auto_redirect;

        foreach (HomeCopy::defaults() as $key => $english) {
            $this->strings[self::field($key)] = (string) ($this->page->strings[$key] ?? '');
        }

        // A draft started earlier (or still running) survives a reload.
        $this->pollAi();
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @return array<string, string> copy key => translation */
    private function translations(): array
    {
        $out = [];
        foreach (array_keys(HomeCopy::defaults()) as $key) {
            $out[$key] = trim((string) ($this->strings[self::field($key)] ?? ''));
        }

        return $out;
    }

    public function save(): void
    {
        $this->slug = strtolower(trim($this->slug));
        $this->validate([
            'language_name'    => 'required|string|max:40',
            'locale'           => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/'],
            'slug'             => ['required', 'regex:/^[a-z]{2}(-[a-z]{2,4})?$/', Rule::unique('landing_pages', 'slug')->ignore($this->page->id)],
            'countries'        => 'nullable|string|max:500',
            'strings.*'        => 'nullable|string|max:2000',
        ]);

        // A placeholder dropped in translation prints a gap where a price or
        // a number of days should be.
        $translations = $this->translations();
        foreach (HomeCopy::defaults() as $key => $english) {
            $value = $translations[$key];
            preg_match_all('/:[a-z]+/', $english, $need);
            if ($value !== '' && ($missing = array_filter($need[0], fn ($p) => ! str_contains($value, $p)))) {
                $this->section = strstr($key, '.', true);
                $this->addError('strings.'.self::field($key), 'Keep '.implode(', ', $missing).' in this translation.');
                return;
            }
        }

        if ($clash = LandingPages::routeClash($this->slug)) {
            $this->addError('slug', "/{$this->slug} is already a page ({$clash}).");
            return;
        }
        $countries = LandingPages::parseCountries($this->countries);
        if (is_string($countries)) {
            $this->addError('countries', $countries);
            return;
        }

        // Two published pages auto-redirecting the same country would race.
        if ($this->auto_redirect && $this->is_published) {
            $taken = LandingPage::published()->where('auto_redirect', true)->where('id', '!=', $this->page->id)->get()
                ->first(fn ($p) => array_intersect((array) $p->countries, $countries));
            if ($taken) {
                $this->addError('auto_redirect', "{$taken->language_name} already auto-redirects ".implode(', ', array_intersect((array) $taken->countries, $countries)).'.');
                return;
            }
        }

        $this->page->update([
            'language_name'    => $this->language_name,
            'locale'           => $this->locale,
            'slug'             => $this->slug,
            'countries'        => $countries,
            'strings'          => array_filter($translations, fn ($v) => $v !== ''),
            'is_published'     => $this->is_published,
            'auto_redirect'    => $this->auto_redirect,
        ]);

        // Its results are in the boxes and now saved; a later reload must
        // not refill boxes the admin has since cleared on purpose.
        if (! $this->aiRunning) {
            cache()->forget(TranslateLandingPage::key($this->page->id));
        }

        session()->flash('success', 'Saved.');
    }

    /** Queue an AI draft of every blank box. */
    public function startAi(): void
    {
        $blank = array_keys(array_filter($this->translations(), fn ($v) => $v === ''));
        $this->aiError = null;

        if (! $blank) {
            session()->flash('success', 'Every box already has a translation.');
            return;
        }
        if (! \App\Models\AppSetting::get('openrouter_api_key')) {
            $this->aiError = 'No OpenRouter API key is set. Add one under Settings › API Keys.';
            return;
        }

        TranslateLandingPage::start($this->page, $blank);
        $this->pollAi();
    }

    /** Merge what the queue has translated so far into the blank boxes. */
    public function pollAi(): void
    {
        $state = TranslateLandingPage::state($this->page->id);
        if (! $state) {
            $this->aiRunning = false;
            return;
        }

        foreach ($state['results'] as $key => $value) {
            $field = self::field($key);
            if (array_key_exists($field, $this->strings) && trim((string) $this->strings[$field]) === '') {
                $this->strings[$field] = $value;
            }
        }

        $wasRunning   = $this->aiRunning;
        $this->aiDone  = (int) $state['done'];
        $this->aiTotal = (int) $state['total'];
        $this->aiError = $state['error'];
        $this->aiRunning = $state['status'] === 'running';

        if ($wasRunning && $state['status'] === 'done') {
            session()->flash('success', 'AI draft done. Read it through, then Save — nothing is stored until you do.');
        }
    }

    public function cancelAi(): void
    {
        cache()->forget(TranslateLandingPage::key($this->page->id));
        $this->aiRunning = false;
    }

    public function clearSection(): void
    {
        foreach (array_keys(HomeCopy::grouped()[$this->section] ?? []) as $key) {
            $this->strings[self::field($key)] = '';
        }
    }

    public function render()
    {
        $grouped = HomeCopy::grouped();
        $filled = fn (string $k) => trim((string) ($this->strings[self::field($k)] ?? '')) !== '';

        return view('livewire.admin.landing-page-form', [
            'grouped'   => $grouped,
            'total'     => count(HomeCopy::defaults()),
            'done'      => count(array_filter(array_keys(HomeCopy::defaults()), $filled)),
            'progress'  => collect($grouped)->map(fn ($keys) => [count(array_filter(array_keys($keys), $filled)), count($keys)]),
            'fields'    => collect($grouped[$this->section] ?? [])
                ->when($this->onlyMissing, fn ($c) => $c->reject(fn ($en, $k) => $filled($k))),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Country Page — '.$this->page->language_name]);
    }
}
