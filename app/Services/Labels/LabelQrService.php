<?php

namespace App\Services\Labels;

use App\Models\Company;
use App\Models\LabelSet;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes that take a chef straight to a print set.
 *
 * Stuck on the chiller door or the station, so scanning replaces finding
 * the outlet, finding the set, and opening it. If the phone isn't signed
 * in the PIN screen appears first and then returns to the set — see
 * LabelStaffAuthenticate.
 */
class LabelQrService
{
    /**
     * The URL a set's QR encodes.
     *
     * Built by hand rather than with route() because these are generated in
     * the MANAGER app, on the main domain, where the subdomain route
     * defaults aren't bound — route() would produce a main-domain URL that
     * sends staff to a login they cannot use.
     */
    public function urlFor(LabelSet $set): string
    {
        $company = Company::find($set->company_id);
        $domain  = config('app.domain');

        if ($domain && $company?->slug) {
            return 'https://' . $company->slug . '.' . $domain . '/labels/sets/' . $set->id;
        }

        // Local dev, where the staff app lives on a path instead.
        return url('/labels-staff/sets/' . $set->id);
    }

    /** SVG data URI, safe to drop straight into an <img src>. */
    public function svgFor(LabelSet $set): string
    {
        return $this->encode($this->urlFor($set));
    }

    public function encode(string $data): string
    {
        $options = new QROptions([
            'version'      => Version::AUTO,
            // Q survives a smudged or partly peeled sticker in a kitchen;
            // the payload is short so the extra redundancy costs nothing.
            'eccLevel'     => EccLevel::Q,
            'outputBase64' => true,
            'scale'        => 6,
            // A quiet zone is not decoration: scanners need the margin to
            // find the code against a busy stainless-steel background.
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($data);
    }
}
