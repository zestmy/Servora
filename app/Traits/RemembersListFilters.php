<?php

namespace App\Traits;

/**
 * A list screen comes back the way you left it.
 *
 * REPORTED ON EMPLOYEES FIRST and asked for everywhere else: open a record,
 * save it, and the list returns having forgotten which branch you were working
 * through. Every one of these modules has a list, forms on their own routes,
 * and a redirect back — and the redirect carries a tab at best, so the filters
 * are gone by the time you land.
 *
 * REMEMBERED RATHER THAN THREADED THROUGH THE FORMS. Employees carries its
 * filters in the URL because there is exactly one form behind it. Inventory has
 * five, Purchasing has seven, and passing them into each — and back out of
 * every save, every cancel and every back arrow — is a lot of surface to get
 * wrong for a convenience. This asks nothing of the forms at all.
 *
 * PER SCREEN, NOT GLOBAL. Filtering Purchasing to one branch says nothing about
 * how you want Stock Management filtered, and one shared key would have every
 * screen lurch to whichever you touched last. The key is the component class,
 * plus a scope for screens whose filters mean different things per tab.
 *
 * "All outlets" is a real answer, so this tests whether a value was STORED
 * rather than whether it is empty: '' means somebody chose All, and a screen
 * that defaults to your own outlet must not overrule that.
 *
 * NOT THE TAB, and not the search box. The tab belongs to the URL, or opening
 * Prep Items would be dragged back to whichever tab you last used. The search
 * box is something you type to find one record, not a place in a list.
 */
trait RemembersListFilters
{
    /** The outlet property. Overridden by screens that name it differently. */
    protected function outletFilterProperty(): string
    {
        return 'outletFilter';
    }

    /**
     * Everything this screen should come back on.
     *
     * The outlet alone by default. A screen with more filters lists them, and
     * gets them all remembered for free.
     *
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return [$this->outletFilterProperty()];
    }

    /**
     * Distinguishes bundles that must not bleed into each other.
     *
     * Stock Management's supplier filter means nothing on the Wastage tab, and
     * a filter restored onto a tab that cannot use it is a filter doing
     * nothing while looking like it is doing something. Screens with tabs
     * return the tab; everything else shares one bundle.
     */
    protected function rememberedFilterScope(): string
    {
        return '';
    }

    protected function rememberedFilterKey(): string
    {
        $scope = $this->rememberedFilterScope();

        return 'list-filters.' . static::class . ($scope !== '' ? '.' . $scope : '');
    }

    /**
     * Restore what this screen was last left on.
     *
     * MUST BE CALLED AFTER THE TAB IS KNOWN on a screen that scopes by tab,
     * or it reads the wrong bundle — and before any default is applied, or the
     * default wins and this looks wired up while doing nothing.
     *
     * @return bool  whether anything was restored, so a screen with its own
     *               default can tell "they chose All" from "they chose
     *               nothing", which an empty string cannot express.
     */
    protected function bootRememberedFilters(): bool
    {
        $stored = session($this->rememberedFilterKey());

        if (! is_array($stored)) {
            return false;
        }

        foreach ($this->rememberedFilters() as $property) {
            if (array_key_exists($property, $stored) && property_exists($this, $property)) {
                $this->{$property} = $stored[$property];
            }
        }

        return true;
    }

    /**
     * Store whatever the screen is currently filtered to.
     *
     * Called from render() rather than an updated() hook on purpose: several of
     * these screens already define updatedOutletFilter(), and a class method
     * silently beats a trait's — so the hook would have been a switch that
     * looked wired up and was not.
     */
    protected function rememberFilters(): void
    {
        $state = [];

        foreach ($this->rememberedFilters() as $property) {
            if (property_exists($this, $property)) {
                $state[$property] = $this->{$property};
            }
        }

        session([$this->rememberedFilterKey() => $state]);
    }
}
