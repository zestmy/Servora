<?php

namespace App\Livewire\Admin;

use App\Models\LandingPage;
use App\Support\Countries;
use App\Support\Marketing\HomeCopy;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Admin › Country Pages: the marketing home page in a local language, one
 * per language, for the countries listed on it. Creating one here opens the
 * translation screen (LandingPageForm).
 */
class LandingPages extends Component
{
    public bool $showModal = false;

    public string $language_name = '';
    public string $locale = '';
    public string $slug = '';
    public string $countries = '';

    /** Common pairs, so the form fills itself. [language, locale, slug, countries] */
    public const PRESETS = [
        'Bahasa Indonesia' => ['id', 'id', 'ID'],
        'ไทย (Thai)'       => ['th', 'th', 'TH'],
        'Tiếng Việt'       => ['vi', 'vi', 'VN'],
        'Filipino'         => ['fil', 'ph', 'PH'],
        '简体中文 (Chinese)' => ['zh-Hans', 'zh', 'CN, SG'],
        '繁體中文 (Chinese)' => ['zh-Hant', 'zh-tw', 'TW, HK, MO'],
        'Bahasa Melayu'    => ['ms', 'ms', 'BN'],
        '日本語 (Japanese)' => ['ja', 'ja', 'JP'],
        '한국어 (Korean)'   => ['ko', 'ko', 'KR'],
        'العربية (Arabic)' => ['ar', 'ar', 'AE, SA, QA, KW, OM, BH'],
    ];

    public function usePreset(string $name): void
    {
        if ($p = self::PRESETS[$name] ?? null) {
            [$this->locale, $this->slug, $this->countries] = $p;
            $this->language_name = $name;
        }
    }

    public function create()
    {
        $this->slug = strtolower(trim($this->slug));
        $this->validate([
            'language_name' => 'required|string|max:40',
            'locale'        => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/'],
            'slug'          => ['required', 'regex:/^[a-z]{2}(-[a-z]{2,4})?$/', Rule::unique('landing_pages', 'slug')],
            'countries'     => 'nullable|string|max:500',
        ], [
            'slug.regex' => 'Two letters, optionally with a region: id, th, zh-tw.',
        ]);

        if ($clash = self::routeClash($this->slug)) {
            $this->addError('slug', "/{$this->slug} is already a page ({$clash}). Pick another.");
            return null;
        }

        $countries = self::parseCountries($this->countries);
        if (is_string($countries)) {
            $this->addError('countries', $countries);
            return null;
        }

        $page = LandingPage::create([
            'language_name' => $this->language_name,
            'locale'        => $this->locale,
            'slug'          => $this->slug,
            'countries'     => $countries,
            'is_published'  => false,
        ]);

        return $this->redirectRoute('admin.landing-pages.edit', ['id' => $page->id], navigate: true);
    }

    public function togglePublished(int $id): void
    {
        $page = LandingPage::findOrFail($id);
        $page->update(['is_published' => ! $page->is_published]);
    }

    public function delete(int $id): void
    {
        LandingPage::whereKey($id)->delete();
        session()->flash('success', 'Country page deleted.');
    }

    /** The name of a route that already answers at /{slug}, if one does. */
    public static function routeClash(string $slug): ?string
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== 'marketing.landing' && trim($route->uri(), '/') === $slug) {
                return $route->getName() ?: $route->uri();
            }
        }

        return null;
    }

    /** @return array<int, string>|string the codes, or an error message */
    public static function parseCountries(string $input): array|string
    {
        $codes = collect(preg_split('/[\s,;]+/', strtoupper($input)))->filter()->unique()->values();

        if ($bad = $codes->first(fn ($c) => ! Countries::exists($c))) {
            return "\"{$bad}\" is not a two-letter country code (ISO 3166, e.g. ID, TH, VN).";
        }

        return $codes->all();
    }

    public function render()
    {
        $total = count(HomeCopy::defaults());

        return view('livewire.admin.landing-pages', [
            'pages' => LandingPage::orderBy('language_name')->get(),
            'total' => $total,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Country Pages']);
    }
}
