<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\Outlet;
use App\Services\Entitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces the plan caps (Free: 1 outlet, 150 market list items, 30 recipes)
 * at the model, so a form, an import, AI capture and a duplicate button are
 * all held to the same number without each remembering to ask.
 *
 * Registered for Outlet, Ingredient and Recipe in AppServiceProvider. Users
 * are checked where they are invited instead: a User row is also created by
 * registration and by linking an existing login, which are not "adding a
 * seat" in the same way.
 */
class PlanLimitObserver
{
    private const METRICS = [
        \App\Models\Outlet::class     => 'outlets',
        \App\Models\Ingredient::class => 'ingredients',
        \App\Models\Recipe::class     => 'recipes',
    ];

    public function creating(Model $model): void
    {
        // An outlet created archived takes no slot.
        if ($model instanceof Outlet && $model->is_active === false) {
            return;
        }

        $this->check($model);
    }

    /** Re-activating an archived outlet takes a slot back. */
    public function updating(Model $model): void
    {
        if ($model instanceof Outlet && $model->isDirty('is_active') && $model->is_active) {
            $this->check($model);
        }
    }

    private function check(Model $model): void
    {
        $metric = self::METRICS[$model::class] ?? null;
        $companyId = $model->company_id ?? Auth::user()?->company_id;

        if (! $metric || ! $companyId || ! ($company = Company::find($companyId))) {
            return;
        }

        app(Entitlements::class)->assertCanAdd($company, $metric);
    }
}
