<?php

namespace App\Traits;

/**
 * A list screen comes back on the outlet you left it on.
 *
 * REPORTED ON EMPLOYEES FIRST and asked for everywhere else: open a record,
 * save it, and the list returns having forgotten which branch you were working
 * through. Every one of these modules has a list, a form on its own route, and
 * a redirect back — and the redirect carries a tab at best, so the filter is
 * gone by the time you land.
 *
 * REMEMBERED RATHER THAN THREADED THROUGH THE FORMS. Employees carries its
 * outlet in the URL because there is exactly one form behind it. Inventory has
 * five, Purchasing has seven, and passing a parameter into each — and back out
 * of every save, every cancel and every back arrow — is a lot of surface to get
 * wrong for a convenience. This asks nothing of the forms at all.
 *
 * PER SCREEN, NOT GLOBAL. Filtering Purchasing to one branch says nothing about
 * how you want Stock Management filtered, and one shared key would have every
 * screen lurch to whichever you touched last. The key is the component class.
 *
 * "All outlets" is a real answer, so this tests whether a value was STORED
 * rather than whether it is empty: '' means somebody chose All, and a screen
 * that defaults to your own outlet must not overrule that.
 */
trait RemembersOutletFilter
{
    /** The property this trait manages. Overridden by screens that name it differently. */
    protected function outletFilterProperty(): string
    {
        return 'outletFilter';
    }

    protected function rememberedOutletKey(): string
    {
        return 'outlet-filter.' . static::class;
    }

    /**
     * Restore the last outlet chosen on THIS screen.
     *
     * @return bool  whether anything was restored, so a screen with its own
     *               default can tell "they chose All" from "they chose
     *               nothing", which an empty string cannot express.
     */
    protected function bootRememberedOutlet(): bool
    {
        $key = $this->rememberedOutletKey();

        if (! session()->has($key)) {
            return false;
        }

        $property = $this->outletFilterProperty();

        $this->{$property} = (string) session($key);

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
    protected function rememberOutlet(): void
    {
        session([$this->rememberedOutletKey() => (string) $this->{$this->outletFilterProperty()}]);
    }
}
