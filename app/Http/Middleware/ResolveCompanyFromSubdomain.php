<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyFromSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $domain = config('app.domain');

        // Skip if no domain configured (local dev on localhost)
        if (!$domain || $host === 'localhost' || $host === '127.0.0.1') {
            return $next($request);
        }

        // Extract subdomain: "acme.servora.com.my" → "acme"
        $subdomain = $this->extractSubdomain($host, $domain);

        // No subdomain (main domain) or www → skip (marketing site or existing behavior)
        if (!$subdomain || $subdomain === 'www') {
            return $next($request);
        }

        $company = Company::where('slug', $subdomain)->where('is_active', true)->first();

        if (!$company) {
            abort(404, 'Company not found.');
        }

        // Store resolved company in the app container and session
        app()->instance('currentCompany', $company);
        session(['subdomain_company_id' => $company->id]);
        session(['lms_company_slug' => $company->slug]);

        return $next($request);
    }

    private function extractSubdomain(string $host, string $domain): ?string
    {
        // Host must end with the configured domain
        if (!str_ends_with($host, $domain)) {
            return null;
        }

        $prefix = substr($host, 0, strlen($host) - strlen($domain));

        // Remove trailing dot: "acme." → "acme"
        $prefix = rtrim($prefix, '.');

        return $prefix ?: null;
    }
}
