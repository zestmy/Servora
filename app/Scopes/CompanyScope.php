<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Try authenticated user's company_id (web or lms guard)
        $user = Auth::check() ? Auth::user() : (Auth::guard('lms')->check() ? Auth::guard('lms')->user() : null);

        if ($user && $user->company_id) {
            $builder->where($model->getTable() . '.company_id', $user->company_id);
            return;
        }

        // For LMS subdomain routes: try subdomain-resolved company
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;
        if ($company) {
            $builder->where($model->getTable() . '.company_id', $company->id);
        }
    }
}
