<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMainDomain
{
    /**
     * Force all non-LMS traffic to the main domain.
     *
     * - servora.com.my          → allowed (main app + marketing)
     * - {slug}.servora.com.my/lms/*  → allowed (LMS only, if company exists)
     * - {slug}.servora.com.my/*      → redirect to servora.com.my (same path)
     * - random.servora.com.my/*      → redirect to servora.com.my
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $domain = config('app.domain');

        // No domain configured (localhost dev) — skip
        if (!$domain || $host === 'localhost' || $host === '127.0.0.1') {
            return $next($request);
        }

        // On the main domain — always allowed
        if ($host === $domain || $host === 'www.' . $domain) {
            return $next($request);
        }

        // Extract subdomain
        if (!str_ends_with($host, '.' . $domain)) {
            return $next($request);
        }

        $subdomain = substr($host, 0, strlen($host) - strlen('.' . $domain));

        // Skip reserved subdomains (app, www)
        if (in_array($subdomain, ['www', 'app'])) {
            // Redirect app.servora.com.my to servora.com.my
            return redirect()->to('https://' . $domain . $request->getRequestUri(), 301);
        }

        // Check if this is a valid company subdomain
        $company = Company::where('slug', $subdomain)->where('is_active', true)->first();

        if (!$company) {
            // Unknown company — redirect to main domain
            return redirect()->to('https://' . $domain, 302);
        }

        // Valid company subdomain — only allow /lms/* paths
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/lms')) {
            return $next($request);
        }

        // Root or any non-LMS path → redirect to LMS login on same subdomain
        return redirect()->to('https://' . $host . '/lms/login', 302);
    }
}
