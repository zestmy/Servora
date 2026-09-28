<?php

namespace App\Livewire\Admin;

use App\Models\LandingPage;
use App\Services\Marketing\LandingTranslator;
use App\Support\Marketing\HomeCopy;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * One country landing page: its settings, and every string of the home page
 * in its language beside the English (HomeCopy). A blank box shows the
 * English on the live page, so a page can go out half translated without a
 * hole in it — the counter says how far along it is.
 *
 * "Draft with AI" fills the blank boxes (LandingTranslator) one batch per
 * request, looped from the browser, so no single request runs anywhere near
 * the server's 60-second limit.
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

    /** Keys left for the AI draft this run, so a key it could not do is not asked for forever. */
    public array $aiQueue = [];
    public ?string $aiError = null;

    private const AI_BATCH = 40;

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

        session()->flash('success', 'Saved.');
    }

    /** Start an AI draft of every blank box. */
    public function startAi(): void
    {
        $this->aiError = null;
        $this->aiQueue = array_keys(array_filter($this->translations(), fn ($v) => $v === ''));
    }

    /**
     * Translate the next batch. Returns whether there is more to do; the
     * view calls it again until there is not.
     */
    public function aiStep(): bool
    {
        if (! $this->aiQueue) {
            return false;
        }

        $batch = array_splice($this->aiQueue, 0, self::AI_BATCH);
        $english = array_intersect_key(HomeCopy::defaults(), array_flip($batch));

        try {
            $done = app(LandingTranslator::class)->translate($english, $this->language_name, $this->locale);
        } catch (\Throwable $e) {
            $this->aiError = $e->getMessage();
            $this->aiQueue = [];
            return false;
        }

        foreach ($done as $key => $value) {
            if (trim((string) ($this->strings[self::field($key)] ?? '')) === '') {
                $this->strings[self::field($key)] = $value;
            }
        }

        if (! $this->aiQueue) {
            session()->flash('success', 'AI draft done. Read it through, then Save — nothing is stored until you do.');
        }

        return (bool) $this->aiQueue;
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
