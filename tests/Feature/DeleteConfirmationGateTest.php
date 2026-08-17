<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every control that destroys a saved record goes through the typed-word
 * dialog, and this test is what stops the next one being added without it.
 *
 * WHY IT SCANS THE BLADE RATHER THAN CLICKING BUTTONS. The failure it guards
 * against is a control being added and simply forgotten — there is no run-time
 * behaviour to assert on a button nobody wrote a test for. A sweep over the
 * source is the only shape that catches an omission, which is the same reason
 * NavPermissionDriftTest reads the menus rather than the rendered page.
 *
 * THE EXEMPT LIST IS THE POINT, not an inconvenience. A new `removeThing`
 * action fails this test until somebody writes down which of the two it is:
 * something that destroys a row (gate it) or something that edits the form on
 * screen and is committed by Save (list it here, with the reason). That
 * decision is exactly what nobody made for the staff list delete.
 */
class DeleteConfirmationGateTest extends TestCase
{
    /** Attribute that routes a control through components/confirm-delete.blade.php. */
    private const GATE = 'data-confirm-delete';

    /**
     * Controls whose name looks destructive but which change nothing on disk
     * until Save. Keyed by "<view>::<method>", valued with the reason.
     *
     * Adding an entry here is a claim that the action does NOT delete a
     * persisted row. If that stops being true, move it to the gate.
     */
    private const NOT_DESTRUCTIVE = [
        // ── Unsaved line items on a form being built. Removing one is
        //    ordinary editing, done many times while composing a document,
        //    and nothing is written until the form is saved.
        'livewire/inventory/prep-item-form.blade.php::removeLine'                => 'unsaved recipe line',
        'livewire/inventory/prep-item-form.blade.php::removeMultiplier'          => 'unsaved yield multiplier',
        'livewire/inventory/prep-item-form.blade.php::removeStep'                => 'unsaved preparation step',
        'livewire/inventory/prep-item-form.blade.php::removeStepImage'           => 'unsaved step image',
        'livewire/inventory/prep-item-form.blade.php::removeNewPresentationImage' => 'upload not yet stored',
        'livewire/inventory/staff-meal-form.blade.php::removeLine'               => 'unsaved line',
        'livewire/inventory/stock-take-form.blade.php::removeLine'               => 'unsaved line',
        'livewire/inventory/transfer-form.blade.php::removeLine'                 => 'unsaved line',
        'livewire/inventory/wastage-form.blade.php::removeLine'                  => 'unsaved line',
        'livewire/kitchen/production-order-form.blade.php::removeLine'           => 'unsaved line',
        'livewire/kitchen/production-recipe-form.blade.php::removeLine'          => 'unsaved recipe line',
        'livewire/kitchen/production-recipe-form.blade.php::removePackagingLine' => 'unsaved packaging line',
        'livewire/kitchen/production-recipe-form.blade.php::removeExtraCostRow'  => 'unsaved cost row',
        'livewire/kitchen/production-recipe-form.blade.php::removeStep'          => 'unsaved preparation step',
        'livewire/kitchen/production-recipe-form.blade.php::removeStepImage'     => 'unsaved step image',
        'livewire/kitchen/production-recipe-form.blade.php::removeNewPresentationImage' => 'upload not yet stored',
        'livewire/purchasing/convert-to-do.blade.php::removeLine'                => 'unsaved line',
        'livewire/purchasing/credit-note-form.blade.php::removeLine'             => 'unsaved line',
        'livewire/purchasing/invoice-receive.blade.php::removeLine'              => 'unsaved line',
        'livewire/purchasing/order-form.blade.php::removeLine'                   => 'unsaved line',
        'livewire/purchasing/purchase-request-form.blade.php::removeLine'        => 'unsaved line',
        'livewire/purchasing/receive-form.blade.php::removeLine'                 => 'unsaved line',
        'livewire/purchasing/stock-transfer-form.blade.php::removeLine'          => 'unsaved line',
        'livewire/recipes/form.blade.php::removeLine'                            => 'unsaved recipe line',
        'livewire/recipes/form.blade.php::removePackagingLine'                   => 'unsaved packaging line',
        'livewire/recipes/form.blade.php::removeExtraCostRow'                    => 'unsaved cost row',
        'livewire/recipes/form.blade.php::removeStep'                            => 'unsaved preparation step',
        'livewire/recipes/form.blade.php::removeStepImage'                       => 'unsaved step image',
        'livewire/recipes/form.blade.php::removeNewDineInImage'                  => 'upload not yet stored',
        'livewire/recipes/form.blade.php::removeNewTakeawayImage'                => 'upload not yet stored',
        'livewire/sales/sales-form.blade.php::removeNewAttachment'               => 'upload not yet stored',
        'livewire/hr/employee-form.blade.php::removeCertification'               => 'unsaved certification row',
        'livewire/ingredients/index.blade.php::removeConversionRow'              => 'unsaved UOM conversion row',
        'livewire/ingredients/index.blade.php::removeSupplierRow'                => 'unsaved supplier row',
        'livewire/hr/attendance-records.blade.php::removeServiceChargeFund'      => 'unsaved fund row',
        'livewire/settings/labour-costs.blade.php::removeAllowance'              => 'unsaved allowance row',
        'livewire/settings/statutory-rates.blade.php::removeBand'                => 'unsaved rate band',
        'livewire/labels/template-designer.blade.php::removeField'               => 'unsaved field on the label design',
        'livewire/labels/print-screen.blade.php::removeLine'                     => 'item in the print queue, not yet printed',
        'livewire/labels/staff/print-labels.blade.php::removeLine'               => 'item in the print queue, not yet printed',
        'livewire/onboarding/wizard.blade.php::removeInvite'                     => 'invitation not yet sent',
        'livewire/training/partials/question-editor.blade.php::removeOption'     => 'unsaved option on the question being edited',
        'livewire/training/course-form.blade.php::removeCover'                   => 'clears the field; the stored image survives until Save',
        'livewire/training/quiz-builder.blade.php::removeMusicFile'              => 'clears the field; the stored track survives until Save',
        'livewire/training/partials/question-editor.blade.php::removeQuestionImage' => 'clears the field; the stored photo survives until Save',

        // ── Not a delete at all. Matched on the word rather than the act:
        //    "clearSelection" closes the report-card panel and puts nothing
        //    back to the database. Same shape as the clearRange/clearApiKey
        //    cases above, in reverse — those hide a destructive act behind a
        //    harmless name, this hides a harmless one behind a loaded name.
        'livewire/training/report-cards.blade.php::clearSelection'               => 'closes the open report card; nothing is removed',

        // ── Calculators. Scratch screens that compute and store nothing.
        'livewire/marketing/menu-engineering-matrix.blade.php::removeRow'        => 'calculator row, nothing stored',
        'livewire/marketing/recipe-cost-calculator.blade.php::removeLine'        => 'calculator row, nothing stored',
        'livewire/marketing/salary-calculator.blade.php::removeAllowance'        => 'calculator row, nothing stored',

        // ── Clearing a field or a selection, not a record.
        'livewire/hr/documents.blade.php::clearSearch'                           => 'resets the search box',
        'livewire/inventory/prep-item-form.blade.php::clearOutletSelection'      => 'unticks outlets on a form',
        'livewire/recipes/form.blade.php::clearOutletSelection'                  => 'unticks outlets on a form',
        'livewire/inventory/prep-item-form.blade.php::clearStepNewImage'         => 'drops a pending upload',
        'livewire/kitchen/production-recipe-form.blade.php::clearStepNewImage'   => 'drops a pending upload',
        'livewire/recipes/form.blade.php::clearStepNewImage'                     => 'drops a pending upload',
        'livewire/labels/print-screen.blade.php::clearTray'                      => 'empties the print queue',
        'livewire/labels/staff/print-labels.blade.php::clearTray'                => 'empties the print queue',
        'livewire/sales/zeoniq-excel-import.blade.php::clearAllMappings'         => 'resets an import mapping before import',

        /*
         * Emptying a cell in the shelf-life grid does delete its rule row —
         * but a rule is a NUMBER, retyped in the cell it was cleared from,
         * and clearing one falls back to the standard figure rather than
         * losing anything. This grid is edited cell by cell; a typed word per
         * cell would make it unusable, which is the failure mode a safety
         * feature has to avoid to stay switched on.
         */
        'livewire/labels/shelf-life-grid.blade.php::clearCell'                   => 'a retypeable number in a settings grid',
    ];

    /** Anything whose method name reads as a deletion is a candidate. */
    private const CANDIDATE = '/delete|destroy|remove|purge|^clear/i';

    private function bladeFiles(): array
    {
        $files = [];
        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );
        foreach ($dir as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The open tag containing $pos, scanned with quote awareness.
     *
     * Line-based slicing gets this wrong: several of these tags carry a
     * message whose Blade expression contains a bare '>' ({{ $charge > 0 ? …
     * }}), and a naive scanner ends the tag in the middle of the attribute
     * that decides whether the control is gated.
     */
    private function enclosingTag(string $text, int $pos): string
    {
        $start = 0;
        foreach (['<button', '<a ', "<a\n", '<form', '<x-'] as $needle) {
            $at = strrpos(substr($text, 0, $pos), $needle);
            if ($at !== false) {
                $start = max($start, $at);
            }
        }

        $quote = null;
        for ($i = $start; $i < strlen($text); $i++) {
            $ch = $text[$i];
            if ($quote !== null) {
                if ($ch === $quote) $quote = null;
            } elseif ($ch === '"' || $ch === "'") {
                $quote = $ch;
            } elseif ($ch === '>') {
                return substr($text, $start, $i - $start + 1);
            }
        }

        return substr($text, $start);
    }

    /** @return array<string, string> key => the tag markup */
    private function candidateControls(): array
    {
        $found = [];

        foreach ($this->bladeFiles() as $path) {
            $text = file_get_contents($path);
            $view = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
            $view = str_replace(DIRECTORY_SEPARATOR, '/', $view);

            preg_match_all('/wire:click(?:\.[a-z]+)*="([a-zA-Z_]\w*)/', $text, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[1] as $match) {
                [$method, $offset] = $match;
                if (! preg_match(self::CANDIDATE, $method)) {
                    continue;
                }
                $found["{$view}::{$method}"] = $this->enclosingTag($text, $offset);
            }
        }

        return $found;
    }

    public function test_every_destructive_control_is_gated_or_declared_harmless(): void
    {
        $ungated = [];

        foreach ($this->candidateControls() as $key => $tag) {
            if (str_contains($tag, self::GATE)) {
                continue;
            }
            if (array_key_exists($key, self::NOT_DESTRUCTIVE)) {
                continue;
            }
            $ungated[] = $key;
        }

        $this->assertSame([], $ungated, implode("\n", array_merge(
            ['These delete controls neither carry ' . self::GATE . ' nor appear in NOT_DESTRUCTIVE:', ''],
            $ungated,
            ['', 'If the action destroys a saved record, add ' . self::GATE . '="<what goes>" to the control.',
             'If it only edits the form on screen, add it to NOT_DESTRUCTIVE with the reason.']
        )));
    }

    /** A stale exemption is a claim nobody is checking any more. */
    public function test_no_exempt_entry_is_left_over(): void
    {
        $keys = array_keys($this->candidateControls());
        $stale = array_diff(array_keys(self::NOT_DESTRUCTIVE), $keys);

        $this->assertSame([], array_values($stale),
            "NOT_DESTRUCTIVE names controls that no longer exist:\n" . implode("\n", $stale));
    }

    /** An empty message would render a dialog that says nothing. */
    public function test_every_gate_carries_a_message(): void
    {
        $empty = [];

        foreach ($this->bladeFiles() as $path) {
            if (str_ends_with($path, 'confirm-delete.blade.php')) {
                continue; // the dialog itself, which names the attribute
            }
            preg_match_all('/' . self::GATE . '="([^"]*)"/', file_get_contents($path), $m);
            foreach ($m[1] as $message) {
                if (trim($message) === '') {
                    $empty[] = $path;
                }
            }
        }

        $this->assertSame([], $empty);
    }

    /**
     * The dialog has to be ON the page for the attribute to mean anything. A
     * layout that renders a delete button without it would cancel the click
     * and never offer the way through — the gate fails closed, so this would
     * present as the button being broken.
     */
    public function test_every_authenticated_layout_renders_the_dialog(): void
    {
        $layouts = glob(resource_path('views/layouts/*.blade.php'));
        $missing = [];

        foreach ($layouts as $layout) {
            if (str_ends_with($layout, 'guest.blade.php')) {
                continue; // login and password reset — nothing to delete
            }
            if (! str_contains(file_get_contents($layout), '<x-confirm-delete')) {
                $missing[] = basename($layout);
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * wire:confirm is Livewire's native window.confirm(). It is fine for
     * "submit this?" and wrong for a delete: its OK button lands under the
     * thumb that just tapped the icon, which is how a member of staff was
     * destroyed from a phone.
     */
    public function test_no_delete_still_uses_the_native_confirm(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $path) {
            preg_match_all('/wire:confirm="([^"]*)"/', file_get_contents($path), $m);
            foreach ($m[1] as $message) {
                if (preg_match('/\bdelete\b|\bdeleting\b|permanently/i', $message)) {
                    $offenders[] = basename($path) . ': ' . $message;
                }
            }
        }

        $this->assertSame([], $offenders, "Use " . self::GATE . " for deletions:\n" . implode("\n", $offenders));
    }
}
