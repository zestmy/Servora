<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\PublicHoliday;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What replacement days an employee has for the public holidays they worked.
 *
 * A credit is DERIVED, never stored. Every active employee earns one day per
 * replaceable holiday that applies to their outlet and falls inside their
 * employment, so the credit is a fact about the holiday — recomputing it costs
 * one query and cannot drift, whereas an employee×holiday table would need
 * backfilling every time somebody joins, moves outlet, or has a join date
 * corrected. Only the SPEND is stored, on `leave_requests.public_holiday_id`.
 *
 * Three things can stop a credit being usable, and the difference matters to
 * the person reading the screen:
 *
 *   spent    — already booked, pending or approved
 *   expired  — the claim window closed before it was taken
 *   upcoming — the holiday has not happened yet
 *
 * "Expired" in particular has to stay VISIBLE rather than being filtered out.
 * A day someone lost by not taking it in time is the one they will argue
 * about, and a balance that silently drops it looks like a bug.
 */
class ReplacementHolidays
{
    public const AVAILABLE = 'available';
    public const SPENT     = 'spent';
    public const EXPIRED   = 'expired';
    public const UPCOMING  = 'upcoming';

    /**
     * Company-and-year holiday lists already fetched, keyed "companyId:year".
     *
     * The HR leave screen asks for a balance for up to fifty employees and
     * every one of them needs the SAME list — only the branch filter and the
     * employment window differ, and both are applied in PHP afterwards. The
     * service is a container singleton, so this lives exactly one request.
     *
     * @var array<string, \Illuminate\Support\Collection<int, PublicHoliday>>
     */
    private array $holidayCache = [];

    /** @var array<int, LeaveSetting> */
    private array $settingsCache = [];

    private function settings(int $companyId): LeaveSetting
    {
        return $this->settingsCache[$companyId] ??= LeaveSetting::forCompany($companyId);
    }

    /** Every replaceable holiday a company recognises in a year, fetched once. */
    private function holidays(int $companyId, int $year): Collection
    {
        return $this->holidayCache["{$companyId}:{$year}"] ??= PublicHoliday::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->active()
            ->replaceable()
            ->forYear($year)
            ->with('outlets:id')
            ->orderBy('date')
            ->get();
    }

    /**
     * Drop the cache. Only needed when holidays change inside one request —
     * the settings screen saving a holiday and re-rendering a balance below
     * it would otherwise show the figure from before the save.
     */
    public function forget(): void
    {
        $this->holidayCache  = [];
        $this->settingsCache = [];
    }

    /**
     * Every credit this employee has from the holidays of one year.
     *
     * Keyed on the HOLIDAY's year, not the year the day off is taken. A credit
     * from a December holiday spent the following January still belongs to the
     * year it was earned in — reporting it against the year it was spent would
     * make both years wrong.
     *
     * @return Collection<int, array{
     *     holiday: PublicHoliday, expires_on: ?Carbon, status: string,
     *     request: ?LeaveRequest, taken_on: ?Carbon
     * }>
     */
    public function creditsFor(Employee $employee, int $year, ?Carbon $asOf = null): Collection
    {
        $asOf     = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $settings = $this->settings($employee->company_id);

        $holidays = $this->earnedBy($employee, $year);

        if ($holidays->isEmpty()) {
            return collect();
        }

        // One query for the whole set rather than one per holiday. Cancelled
        // and rejected requests free the credit again, which is what
        // `committing()` already means everywhere else in leave.
        $spent = LeaveRequest::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->whereIn('public_holiday_id', $holidays->pluck('id'))
            ->committing()
            ->get()
            ->keyBy('public_holiday_id');

        return $holidays->map(function (PublicHoliday $holiday) use ($spent, $settings, $asOf) {
            $request = $spent[$holiday->id] ?? null;
            $expires = $holiday->expiresOn($settings);

            $status = match (true) {
                $request !== null                            => self::SPENT,
                $holiday->date->gt($asOf)                    => self::UPCOMING,
                $expires !== null && $expires->lt($asOf)     => self::EXPIRED,
                default                                      => self::AVAILABLE,
            };

            return [
                'holiday'    => $holiday,
                'expires_on' => $expires,
                'status'     => $status,
                'request'    => $request,
                'taken_on'   => $request?->start_date,
            ];
        })->values();
    }

    /**
     * The holidays that earn this employee a credit, in date order.
     *
     * Three filters, all of which have bitten a real payroll somewhere: the
     * holiday must be replaceable (otherwise the company paid for it instead),
     * it must apply at the employee's outlet (state holidays are not national
     * ones), and it must fall inside their employment — crediting somebody for
     * Chinese New Year two months before they were hired is the kind of error
     * that only surfaces when they try to spend it.
     *
     * @return Collection<int, PublicHoliday>
     */
    public function earnedBy(Employee $employee, int $year): Collection
    {
        $joined = $employee->join_date?->copy()->startOfDay();
        $until  = $employee->employedUntil()?->copy()->startOfDay();

        // The company's list is fetched once per request; the two filters that
        // vary per person are applied here rather than in SQL, so fifty
        // employees on the leave screen share one query instead of fifty.
        return $this->holidays($employee->company_id, $year)
            ->filter(function (PublicHoliday $h) use ($employee, $joined, $until) {
                if ($joined && $h->date->lt($joined)) {
                    return false;
                }

                if ($until && $h->date->gt($until)) {
                    return false;
                }

                return $h->appliesToOutlet($employee->outlet_id);
            })
            ->values();
    }

    /**
     * Credits that could still be spent — available now, or on a holiday that
     * has not happened yet so the day off can be booked in advance.
     *
     * @return Collection<int, array{holiday: PublicHoliday, expires_on: ?Carbon, status: string, request: ?LeaveRequest, taken_on: ?Carbon}>
     */
    public function availableFor(Employee $employee, int $year, ?Carbon $asOf = null): Collection
    {
        return $this->creditsFor($employee, $year, $asOf)
            ->filter(fn ($c) => in_array($c['status'], [self::AVAILABLE, self::UPCOMING], true))
            ->values();
    }

    /**
     * Every unspent credit across the years the employee could still be
     * drawing on — what the apply form offers.
     *
     * Last year is included because a December holiday under a 90-day window
     * is claimable well into January, and an apply form that only knows about
     * the current year would tell somebody they had nothing on 2 January.
     *
     * @return Collection<int, array{holiday: PublicHoliday, expires_on: ?Carbon, status: string, request: ?LeaveRequest, taken_on: ?Carbon}>
     */
    public function claimable(Employee $employee, ?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $year = (int) $asOf->format('Y');

        return collect([$year - 1, $year, $year + 1])
            ->flatMap(fn (int $y) => $this->availableFor($employee, $y, $asOf))
            ->sortBy(fn ($c) => $c['holiday']->date->toDateString())
            ->values();
    }

    /**
     * Whether a specific credit may be spent on a specific date, as a sentence
     * naming the problem, or null when it is fine.
     *
     * The window bounds the DAY OFF, not the application. "Claimable within 90
     * days" is a promise about when you get your day back; letting someone
     * apply on day 89 for a date the following March would make the setting
     * mean nothing.
     */
    public function reasonCannotTake(PublicHoliday $holiday, Carbon $leaveDate, Employee $employee): ?string
    {
        $leaveDate = $leaveDate->copy()->startOfDay();
        $settings  = $this->settings($employee->company_id);

        if (! $holiday->is_replaceable) {
            return $holiday->name . ' is paid at the public holiday rate rather than replaced with a day off.';
        }

        if ($leaveDate->lt($holiday->date)) {
            return 'A replacement cannot be taken before the holiday it replaces — '
                . $holiday->name . ' falls on ' . $holiday->date->format('j M Y') . '.';
        }

        $expires = $holiday->expiresOn($settings);

        if ($expires !== null && $leaveDate->gt($expires)) {
            return 'The replacement for ' . $holiday->name . ' has to be taken by '
                . $expires->format('j M Y') . '.';
        }

        return null;
    }

    /**
     * Why this employee cannot apply for a replacement day right now, or null.
     *
     * Says which of the three it is, because "no replacement days available"
     * on its own reads as broken to somebody who worked four public holidays
     * and knows it.
     */
    public function blockedReason(Employee $employee, ?Carbon $asOf = null): ?string
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        if ($this->claimable($employee, $asOf)->isNotEmpty()) {
            return null;
        }

        $year    = (int) $asOf->format('Y');
        $credits = collect([$year - 1, $year])
            ->flatMap(fn (int $y) => $this->creditsFor($employee, $y, $asOf));

        if ($credits->isEmpty()) {
            return 'No public holidays have been recorded for your branch yet, so there is nothing to replace.';
        }

        if ($credits->every(fn ($c) => $c['status'] === self::SPENT)) {
            return 'Every replacement day you have earned is already booked.';
        }

        return 'Your replacement days have passed their claim window.';
    }

    /**
     * Counts for the balance table: earned, spent, still available, lost.
     *
     * @return array{entitled: float, taken: float, pending: float, expired: float, remaining: float}
     */
    public function summary(Employee $employee, int $year, ?Carbon $asOf = null): array
    {
        $credits = $this->creditsFor($employee, $year, $asOf);

        $taken = $credits->filter(fn ($c) => $c['request']?->status === LeaveRequest::APPROVED)->count();
        $pend  = $credits->filter(fn ($c) => $c['request']?->status === LeaveRequest::PENDING)->count();
        $lost  = $credits->where('status', self::EXPIRED)->count();

        return [
            'entitled'  => (float) $credits->count(),
            'taken'     => (float) $taken,
            'pending'   => (float) $pend,
            'expired'   => (float) $lost,
            // Expired days are gone: counting them as remaining would offer a
            // balance the apply form then refuses, which is worse than zero.
            'remaining' => (float) ($credits->count() - $taken - $pend - $lost),
        ];
    }
}
