<?php

namespace App\Services\Training;

use App\Models\TrainingAttempt;
use App\Models\TrainingCertificate;

/**
 * Issue, re-issue and revoke certificates.
 *
 * ONE VALID CERTIFICATE PER (TRAINEE, COURSE). Passing the food-safety quiz a
 * second time should update the certificate, not print a second one — a staff
 * file with four certificates for the same course is a file nobody can answer
 * "are they current?" from, which is the only question a certificate exists to
 * answer.
 *
 * The serial is generated once and never regenerated on re-issue, for the same
 * reason: it is what the certificate is CHECKED against, and an auditor holding
 * a printout must be able to find it.
 */
class CertificateService
{
    /**
     * Issue for a passed attempt, or update the one already held.
     *
     * Returns null when the attempt did not pass or the quiz does not issue
     * certificates — callers treat "no certificate" as an ordinary outcome
     * rather than a failure, because for most quizzes it is.
     */
    public function issueFor(TrainingAttempt $attempt): ?TrainingCertificate
    {
        $quiz = $attempt->quiz;

        if (! $attempt->passed || ! $quiz?->issues_certificate || ! $attempt->lms_user_id) {
            return null;
        }

        $course  = $quiz->course;
        $trainee = $attempt->trainee;

        if (! $trainee) {
            return null;
        }

        $existing = TrainingCertificate::query()
            ->where('lms_user_id', $trainee->id)
            ->where('training_course_id', $course?->id)
            ->whereNull('revoked_at')
            ->first();

        // A re-pass beats a lapsed certificate, so the expiry is recomputed
        // from today rather than left where it was.
        $expiresOn = $course?->recertify_months
            ? now()->addMonths($course->recertify_months)->toDateString()
            : null;

        if ($existing) {
            // Frozen fields are refreshed here and only here: this IS a new
            // award of the same certificate, so the name and title it carries
            // should be the ones true at the moment it was re-earned.
            $existing->forceFill([
                'training_quiz_id'    => $quiz->id,
                'training_attempt_id' => $attempt->id,
                'recipient_name'      => $trainee->name,
                'title'               => $course?->title ?? $quiz->title,
                'percent'             => $attempt->percent,
                'issued_at'           => now(),
                'expires_on'          => $expiresOn,
            ])->save();

            return $existing;
        }

        return TrainingCertificate::create([
            'company_id'          => $attempt->company_id,
            'lms_user_id'         => $trainee->id,
            'training_course_id'  => $course?->id,
            'training_quiz_id'    => $quiz->id,
            'training_attempt_id' => $attempt->id,
            'serial'              => $this->newSerial(),
            'recipient_name'      => $trainee->name,
            'title'               => $course?->title ?? $quiz->title,
            'percent'             => $attempt->percent,
            'issued_at'           => now(),
            'expires_on'          => $expiresOn,
        ]);
    }

    public function revoke(TrainingCertificate $certificate): TrainingCertificate
    {
        $certificate->forceFill(['revoked_at' => now()])->save();

        return $certificate;
    }

    public function reinstate(TrainingCertificate $certificate): TrainingCertificate
    {
        $certificate->forceFill(['revoked_at' => null])->save();

        return $certificate;
    }

    /**
     * A serial somebody can read down a phone.
     *
     * Year-prefixed so its age is visible without a lookup, and the random half
     * drawn from an alphabet with the ambiguous glyphs already removed — see
     * token() below.
     */
    public function newSerial(): string
    {
        do {
            $serial = 'CERT-' . now()->format('Y') . '-' . $this->token();
        } while (TrainingCertificate::withoutGlobalScopes()->where('serial', $serial)->exists());

        return $serial;
    }

    /**
     * Eight characters with no 0/O or 1/I/L in them.
     *
     * These get read aloud and typed back in. Ambiguous glyphs turn a
     * verification into a guessing game, and a certificate nobody can look up
     * is not proof of anything.
     */
    private function token(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $token    = '';

        for ($i = 0; $i < 8; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $token;
    }
}
