<?php

namespace App\Livewire\Hr\Concerns;

use App\Livewire\Hr\AttendanceRecords;
use Carbon\Carbon;

/**
 * The period picker shared by the Attendance Record grid and the Service
 * Charge page: a calendar month by default, or a custom from–to range.
 *
 * One implementation because the two screens are about the same dates — a
 * service charge pool is keyed on the exact period the grid shows, and two
 * pickers that clamped or stepped differently would leave a pool saved
 * against dates the other screen can never select.
 */
trait PicksAttendancePeriod
{
    public string $periodMode = 'month'; // 'month' | 'range'
    public string $month      = '';      // Y-m
    public string $rangeFrom  = '';
    public string $rangeTo    = '';

    protected function initPeriod(): void
    {
        $this->month     = now()->format('Y-m');
        $this->rangeFrom = now()->startOfMonth()->format('Y-m-d');
        $this->rangeTo   = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Resolve the current period to [from, to], clamped to MAX_DAYS so the
     * grid stays renderable regardless of what the range inputs hold.
     */
    public function period(): array
    {
        if ($this->periodMode === 'month') {
            try {
                $from = Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
            } catch (\Throwable $e) {
                $from = now()->startOfMonth();
                $this->month = $from->format('Y-m');
            }
            return [$from->copy(), $from->copy()->endOfMonth()];
        }

        try {
            $from = Carbon::parse($this->rangeFrom)->startOfDay();
            $to   = Carbon::parse($this->rangeTo)->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        }
        if ($to->lt($from)) $to = $from->copy();
        if ($from->diffInDays($to) >= AttendanceRecords::MAX_DAYS) {
            $to = $from->copy()->addDays(AttendanceRecords::MAX_DAYS - 1);
        }
        $this->rangeFrom = $from->format('Y-m-d');
        $this->rangeTo   = $to->format('Y-m-d');

        return [$from, $to];
    }

    public function previousPeriod(): void
    {
        [$from, $to] = $this->period();
        if ($this->periodMode === 'month') {
            $this->month = $from->subMonthNoOverflow()->format('Y-m');
        } else {
            $days = (int) $from->diffInDays($to) + 1;
            $this->rangeFrom = $from->copy()->subDays($days)->format('Y-m-d');
            $this->rangeTo   = $to->copy()->subDays($days)->format('Y-m-d');
        }
    }

    public function nextPeriod(): void
    {
        [$from, $to] = $this->period();
        if ($this->periodMode === 'month') {
            $this->month = $from->addMonthNoOverflow()->format('Y-m');
        } else {
            $days = (int) $from->diffInDays($to) + 1;
            $this->rangeFrom = $from->copy()->addDays($days)->format('Y-m-d');
            $this->rangeTo   = $to->copy()->addDays($days)->format('Y-m-d');
        }
    }
}
