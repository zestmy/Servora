<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Nothing picks an outlet just because it exists.
 *
 *     $user->activeOutletId() ?: Outlet::where('company_id', $id)->value('id')
 *
 * The tail is "or failing that, WHICHEVER OUTLET COMES FIRST". It reads like a
 * safe default and is not one: it files a purchase order, a delivery, a credit
 * note, a sales record or a par level against a branch nobody chose, and puts
 * a wrong number in that branch's reports with nothing on screen to say so.
 * PicksRecordOutlet's docblock already named it — the default it "replaces",
 * which "silently filed every multi-outlet record under the wrong location".
 *
 * THIS TEST EXISTS BECAUSE GREP MISSED IT. The sites were removed in two
 * passes; the second was reported complete on the strength of a single-line
 * regex, and four more survived in Sales purely by being wrapped across lines.
 * A structural check does not care about line breaks.
 *
 * When there is genuinely nothing to file against, the answer is to refuse
 * (writes) or to leave it unset and let the screen's own picker ask (reads) —
 * never to choose on the user's behalf.
 */
class NoArbitraryOutletFallbackTest extends TestCase
{
    /**
     * Legitimately reads back the one outlet the wizard is creating, rather
     * than defaulting to an arbitrary one out of several.
     */
    private const ALLOWED = [
        'app/Livewire/Onboarding/Wizard.php',
    ];

    /**
     * Comments are stripped before matching, so prose describing the
     * anti-pattern — including this file's own explanation of it, and the
     * docblock on RequiresActiveOutlet — is not mistaken for the thing.
     */
    private function code(string $path): string
    {
        $out = '';
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return preg_replace('/\s+/', ' ', $out);
    }

    /** @return array<string, string> path => offending snippet */
    private function offenders(): array
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            // Whitespace-insensitive, so wrapping across lines cannot hide it.
            $source = $this->code($file->getPathname());

            // "?? / ?: Outlet::…->value('id')" and "?? / ?: Outlet::…->first()"
            $pattern = '/(\?\?|\?:)\s*Outlet::[^;]*?->\s*(value\(\s*[\'"]id[\'"]\s*\)|first\(\))/';

            if (preg_match($pattern, $source, $m)) {
                $found[$relative] = trim($m[0]);
            }
        }

        return $found;
    }

    public function test_no_screen_falls_back_to_whichever_outlet_exists(): void
    {
        $offenders = $this->offenders();

        $this->assertSame([], $offenders, "Arbitrary outlet fallback found in:\n" . implode(
            "\n",
            array_map(fn ($p, $snip) => "  {$p}\n      {$snip}", array_keys($offenders), $offenders)
        ) . "\n\nRefuse (writes) or leave it unset for the picker to ask (reads).");
    }

    /** The detector must actually detect — including the wrapped form. */
    public function test_the_check_catches_both_the_inline_and_wrapped_shapes(): void
    {
        $pattern = '/(\?\?|\?:)\s*Outlet::[^;]*?->\s*(value\(\s*[\'"]id[\'"]\s*\)|first\(\))/';

        $inline  = preg_replace('/\s+/', ' ',
            "\$id = \$user->activeOutletId() ?: Outlet::where('company_id', \$c)->value('id');");

        $wrapped = preg_replace('/\s+/', ' ', "\$id = \$this->outletFilter\n"
            . "    ?: \$user->activeOutletId()\n"
            . "    ?: Outlet::where('company_id', \$user->company_id)->value('id');");

        $this->assertMatchesRegularExpression($pattern, $inline);
        $this->assertMatchesRegularExpression($pattern, $wrapped,
            'The wrapped form is exactly the one that escaped the original grep.');

        // And must not fire on legitimate scoped lookups.
        $ok = preg_replace('/\s+/', ' ',
            "\$outlet = Outlet::where('company_id', \$id)->where('is_active', true)->get();");
        $this->assertDoesNotMatchRegularExpression($pattern, $ok);
    }
}
