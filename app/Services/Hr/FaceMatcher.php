<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use App\Scopes\CompanyScope;

/**
 * Compares a face descriptor from the browser against the ones enrolled for
 * an employee.
 *
 * The comparison runs HERE, on the server, even though the descriptor is
 * computed on the device. The phone is the employee's own and its JavaScript
 * is theirs to edit; a client that reports its own verdict is a client that
 * can report "matched" without a camera. What the device is trusted with is
 * turning a picture into a vector — a job that has to be local so the picture
 * itself never has to be uploaded to be checked — and nothing more.
 */
class FaceMatcher
{
    /**
     * Best (lowest) distance to any enrolled descriptor, or null when there
     * is nothing to compare against.
     *
     * Lower is more similar: 0.0 is the same image, and faces of two
     * different people typically land above 0.6.
     */
    public function bestDistance(Employee $employee, array $descriptor): ?float
    {
        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            return null;
        }

        $enrolled = $this->enrolledFor($employee);

        $best = null;

        foreach ($enrolled as $row) {
            $candidate = $row->descriptor;

            // A row written by an older model, or corrupted, is skipped
            // rather than compared. Mixing model outputs produces a number
            // that looks like a distance and means nothing.
            if (! EmployeeFaceDescriptor::isValidDescriptor($candidate)) {
                continue;
            }

            $distance = self::euclidean($descriptor, $candidate);

            if ($best === null || $distance < $best) {
                $best = $distance;
            }
        }

        return $best;
    }

    public function hasEnrolment(Employee $employee): bool
    {
        return $this->enrolledFor($employee)->isNotEmpty();
    }

    /**
     * Descriptors for this employee produced by the model currently in use.
     *
     * CompanyScope is dropped and company_id matched explicitly: the staff
     * clock app runs with no authenticated user, where the scope resolves
     * from the subdomain binding at best and matches nothing at worst.
     */
    private function enrolledFor(Employee $employee)
    {
        return EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->get();
    }

    /** Straight-line distance between two equal-length vectors. */
    public static function euclidean(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $component) {
            $diff = (float) $component - (float) ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
