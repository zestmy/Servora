<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Roster;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class RosterPdfService
{
    /**
     * Generate PDF for a roster.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public static function generate(Roster $roster)
    {
        $roster->load(['outlet', 'section', 'entries.employee', 'entries.station', 'dayRemarks']);
        $company = Company::find($roster->company_id);

        $weekDays = self::getWeekDays($roster->week_start_date, $roster->week_end_date);
        $entriesGrouped = self::groupEntriesByEmployee($roster);
        $dayRemarks = $roster->dayRemarks->keyBy(fn ($r) => $r->day_date->format('Y-m-d'));

        $periodLabel = $roster->week_start_date->format('M d') . ' - ' . $roster->week_end_date->format('M d, Y');

        return Pdf::loadView('pdf.duty-roster', compact(
            'roster', 'company', 'weekDays', 'entriesGrouped', 'dayRemarks', 'periodLabel'
        ))->setPaper('a4', 'landscape');
    }

    /**
     * Generate PDF output as string.
     */
    public static function generateOutput(Roster $roster): string
    {
        return self::generate($roster)->output();
    }

    /**
     * Get array of dates for the roster week.
     */
    protected static function getWeekDays(Carbon $start, Carbon $end): array
    {
        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'dayName' => $current->format('D'),
                'dayNum' => $current->format('j'),
                'fullDate' => $current->format('M j'),
            ];
            $current->addDay();
        }

        return $days;
    }

    /**
     * Group roster entries by employee.
     * Returns: [employee_id => ['employee' => Employee, 'entries' => [date => RosterEntry], 'sort_order' => int]]
     */
    protected static function groupEntriesByEmployee(Roster $roster): array
    {
        $grouped = [];
        $employeeSortOrders = [];

        foreach ($roster->entries as $entry) {
            $empId = $entry->employee_id;
            $dateKey = $entry->day_date->format('Y-m-d');

            if (!isset($grouped[$empId])) {
                $grouped[$empId] = [
                    'employee' => $entry->employee,
                    'entries' => [],
                    'total_hours' => 0,
                    'total_ot' => 0,
                    'sort_order' => $entry->sort_order ?? 0,
                ];
                $employeeSortOrders[$empId] = $entry->sort_order ?? 0;
            }

            // Keep track of minimum sort_order for this employee
            if (($entry->sort_order ?? 0) < $employeeSortOrders[$empId]) {
                $employeeSortOrders[$empId] = $entry->sort_order ?? 0;
                $grouped[$empId]['sort_order'] = $entry->sort_order ?? 0;
            }

            $grouped[$empId]['entries'][$dateKey] = $entry;
            $grouped[$empId]['total_hours'] += (float) $entry->hours_worked;
            $grouped[$empId]['total_ot'] += (float) $entry->planned_ot;
        }

        // Sort by sort_order (same as roster UI)
        uasort($grouped, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $grouped;
    }
}
