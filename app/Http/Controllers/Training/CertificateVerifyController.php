<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCertificate;
use App\Scopes\CompanyScope;

/**
 * The page behind the QR code on a printed certificate.
 *
 * Public and loginless on purpose: the person scanning is a THIRD PARTY — an
 * auditor, a health inspector, the next employer — who has the paper in hand
 * and wants to know whether it is genuine and still current. A login wall
 * here defeats the entire feature.
 *
 * What it shows is exactly what the paper already shows (name, course, dates,
 * issuer) plus the one thing the paper cannot: whether the record still
 * stands. Nothing more is exposed, and serials are unique random-suffixed
 * strings, not enumerable ids.
 *
 * An unknown serial renders the same page in its "not found" state with a
 * 404 status — a verifier's failure result, not an error screen.
 */
class CertificateVerifyController extends Controller
{
    public function show(string $serial)
    {
        // No signed-in company decides what a public visitor may check.
        $certificate = TrainingCertificate::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'course:id,title'])
            ->where('serial', $serial)
            ->first();

        return response()
            ->view('certificate-verify', ['certificate' => $certificate])
            ->setStatusCode($certificate ? 200 : 404);
    }
}
