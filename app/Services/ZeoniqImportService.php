<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ZeoniqImportService
{
    /**
     * Parse a Zeoniq Session Sales Excel file.
     * Returns structured data with session breakdowns per date/outlet.
     *
     * Handles two formats:
     * 1. Format A (Date > Outlet > Session): Business Date first, then Outlet, then Sessions
     * 2. Format B (Session > Date): Session first, then Business Date with outlet in data rows
     */
    public function parseSessionSalesExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Detect format by checking first few rows
        $format = $this->detectSessionSalesFormat($data);

        if ($format === 'session_first') {
            return $this->parseSessionFirstFormat($data);
        }

        return $this->parseDateFirstFormat($data);
    }

    /**
     * Detect the session sales format (date_first or session_first).
     */
    private function detectSessionSalesFormat(array $data): string
    {
        foreach ($data as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));

            // Format B: Session header appears in col0 before any Business Date in col0
            if (preg_match('/^Session \(Order Start Time\):/i', $col0)) {
                return 'session_first';
            }

            // Format A: Business Date appears in col0
            if (preg_match('/^Business Date:/i', $col0)) {
                return 'date_first';
            }
        }

        return 'date_first';
    }

    /**
     * Parse Format A: Date > Outlet > Session structure.
     */
    private function parseDateFirstFormat(array $data): array
    {
        $departmentColumnMap = $this->detectDepartmentColumnMap($data);
        $results = [];
        $currentDate = null;
        $currentOutlet = null;
        $currentSession = null;
        $sessionData = [];

        foreach ($data as $rowIndex => $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            $col2 = trim((string) ($row[2] ?? ''));
            $col3 = trim((string) ($row[3] ?? ''));

            // Detect Business Date row
            if (preg_match('/^Business Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})/', $col0, $m)) {
                // Save previous date data
                if ($currentDate && $currentOutlet && !empty($sessionData)) {
                    $results[] = $this->buildDateRecord($currentDate, $currentOutlet, $sessionData);
                }
                $currentDate = $this->parseZeoniqDate($m[1]);
                $currentOutlet = null;
                $sessionData = [];
                continue;
            }

            // Detect Outlet row
            if (preg_match('/^Outlet:\s*(.+)/', $col1, $m)) {
                $currentOutlet = trim($m[1]);
                continue;
            }

            // Detect Session row
            if (preg_match('/^Session \(Order Start Time\):\s*(.+)/', $col2, $m)) {
                $sessionInfo = trim($m[1]);
                $currentSession = $this->mapZeoniqSessionToMealPeriod($sessionInfo);
                continue;
            }

            // Detect Subtotal row for a session
            if ($col3 === 'Subtotal' && $currentSession) {
                $transCount = $this->parseNumber($row[4] ?? 0);
                $grossAmount = $this->parseNumber($row[5] ?? 0);
                $discount = $this->parseNumber($row[6] ?? 0);
                $netSales = $this->parseNumber($row[7] ?? 0);
                $tax = $this->parseNumber($row[8] ?? 0);
                $charges = $this->parseNumber($row[9] ?? 0);
                $guestCount = $this->parseNumber($row[10] ?? 0);

                // Extract department sales data
                $departments = $this->extractDepartmentsFromRow($row, $departmentColumnMap);

                if (!isset($sessionData[$currentSession])) {
                    $sessionData[$currentSession] = [
                        'meal_period' => $currentSession,
                        'transactions' => $transCount,
                        'pax' => $guestCount > 0 ? (int) $guestCount : (int) $transCount,
                        'gross_revenue' => $grossAmount,
                        'discount_amount' => $discount,
                        'net_sales' => $netSales,
                        'tax_amount' => $tax,
                        'service_charges' => $charges,
                        'total_sales' => $netSales + $tax + $charges,
                        'departments' => $departments,
                    ];
                }
                $currentSession = null;
                continue;
            }
        }

        // Save last date data
        if ($currentDate && $currentOutlet && !empty($sessionData)) {
            $results[] = $this->buildDateRecord($currentDate, $currentOutlet, $sessionData);
        }

        return $results;
    }

    /**
     * Parse Format B: Session > Date structure (with Pax/Guest Count).
     * In this format, data is grouped by Session first, then by Date.
     * Outlet is found in the data rows (column 3).
     */
    private function parseSessionFirstFormat(array $data): array
    {
        // Collect all date+session combinations
        $departmentColumnMap = $this->detectDepartmentColumnMap($data);
        $dateSessionData = [];
        $currentSession = null;
        $currentDate = null;
        $currentOutlet = null;

        foreach ($data as $rowIndex => $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            $col2 = trim((string) ($row[2] ?? ''));
            $col3 = trim((string) ($row[3] ?? ''));

            // Detect Session header in col0
            if (preg_match('/^Session \(Order Start Time\):\s*(.+)/', $col0, $m)) {
                $sessionInfo = trim($m[1]);
                $currentSession = $this->mapZeoniqSessionToMealPeriod($sessionInfo);
                $currentDate = null;
                continue;
            }

            // Detect Business Date in col1
            if (preg_match('/^Business Date:\s*(\d{1,2}\/\d{1,2}\/\d{4})/', $col1, $m)) {
                $currentDate = $this->parseZeoniqDate($m[1]);
                $currentOutlet = null;
                continue;
            }

            // Detect outlet from hourly data rows (outlet code in col3 like "W001-KLCC")
            if ($currentDate && $col3 && $col3 !== 'Subtotal' && preg_match('/^[A-Z]\d{3}/', $col3)) {
                $currentOutlet = $col3;
            }

            // Detect Subtotal row - this ends a date's session data
            if ($col3 === 'Subtotal' && $currentSession && $currentDate) {
                $transCount = $this->parseNumber($row[4] ?? 0);
                $grossAmount = $this->parseNumber($row[5] ?? 0);
                $discount = $this->parseNumber($row[6] ?? 0);
                $netSales = $this->parseNumber($row[7] ?? 0);
                $tax = $this->parseNumber($row[8] ?? 0);
                $charges = $this->parseNumber($row[9] ?? 0);
                $guestCount = $this->parseNumber($row[10] ?? 0);

                // Extract department sales data
                $departments = $this->extractDepartmentsFromRow($row, $departmentColumnMap);

                // Skip if this is a session-level subtotal (no date context yet set after session change)
                // or a daily subtotal (second consecutive subtotal)
                if ($transCount > 0 && $currentOutlet) {
                    $key = $currentDate . '|' . $currentOutlet;

                    if (!isset($dateSessionData[$key])) {
                        $dateSessionData[$key] = [
                            'date' => $currentDate,
                            'outlet_code' => $currentOutlet,
                            'sessions' => [],
                        ];
                    }

                    // Only add if this session isn't already recorded for this date
                    $existingMealPeriods = array_column($dateSessionData[$key]['sessions'], 'meal_period');
                    if (!in_array($currentSession, $existingMealPeriods)) {
                        $dateSessionData[$key]['sessions'][] = [
                            'meal_period' => $currentSession,
                            'transactions' => (int) $transCount,
                            'pax' => $guestCount > 0 ? (int) $guestCount : (int) $transCount,
                            'gross_revenue' => $grossAmount,
                            'discount_amount' => $discount,
                            'net_sales' => $netSales,
                            'tax_amount' => $tax,
                            'service_charges' => $charges,
                            'total_sales' => $netSales + $tax + $charges,
                            'departments' => $departments,
                        ];
                    }
                }

                // Clear date after processing its subtotal
                $currentDate = null;
                continue;
            }
        }

        return array_values($dateSessionData);
    }

    /**
     * Parse a Zeoniq Daily Summary Excel/CSV file.
     * Returns one record per date with aggregate totals.
     */
    public function parseDailySummaryExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Detect department columns once from the header (robust to a varying
        // number of session columns shifting the Department block sideways).
        $departmentColumnMap = $this->detectDepartmentColumnMap($data);

        $results = [];
        $headerRow = null;
        $headerMap = [];
        $dateColumnIndex = 0; // Track which column has the dates
        $currentOutlet = null; // Track current outlet from "Outlet: XXX" rows
        $sessionColumnMap = []; // Dynamically detected session columns

        foreach ($data as $rowIndex => $row) {
            // Find header row (contains "Business Date" as a standalone column header, not in filters/descriptions)
            if ($headerRow === null) {
                foreach ($row as $colIdx => $cell) {
                    $cellValue = trim((string) $cell);
                    // Match exact "Business Date" or very close variants, not filters
                    if (strcasecmp($cellValue, 'Business Date') === 0 ||
                        preg_match('/^Business\s+Date$/i', $cellValue)) {
                        $headerRow = $rowIndex;
                        $dateColumnIndex = $colIdx; // Remember which column has dates

                        // Build header map from current row
                        foreach ($row as $idx => $header) {
                            $h = strtolower(trim((string) $header));
                            if ($h) {
                                $headerMap[$h] = $idx;
                            }
                        }

                        // Also read next row for sub-headers (multi-row header structure)
                        $nextRow = $data[$rowIndex + 1] ?? [];
                        foreach ($nextRow as $idx => $header) {
                            $h = strtolower(trim((string) $header));
                            if ($h && !isset($headerMap[$h])) {
                                $headerMap[$h] = $idx;
                            }
                        }

                        // Detect session columns from the session header row
                        // Session names are in the same row as "Business Date" or the row after
                        // Check current row first, then the row after
                        $sessionColumnMap = $this->detectSessionColumns($row);
                        if (empty($sessionColumnMap)) {
                            $sessionColumnMap = $this->detectSessionColumns($nextRow);
                        }
                        // Also check the row before (some formats have session names above)
                        if (empty($sessionColumnMap) && $rowIndex > 0) {
                            $prevRow = $data[$rowIndex - 1] ?? [];
                            $sessionColumnMap = $this->detectSessionColumns($prevRow);
                        }

                        break;
                    }
                }
                if ($headerRow !== null) continue;
            }

            // Skip if no headers found yet
            if ($headerRow === null) continue;

            // Check for "Outlet: XXX" rows
            $col0 = trim((string) ($row[0] ?? ''));
            if (preg_match('/^Outlet:\s*(.+)/', $col0, $m)) {
                $currentOutlet = trim($m[1]);
                continue;
            }

            // Try to parse date from the date column (accepts D/M/YYYY format or Excel serial number)
            $dateValue = $row[$dateColumnIndex] ?? '';
            $isDate = false;

            // Check if it's a formatted date string
            if (is_string($dateValue) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($dateValue))) {
                $isDate = true;
            }
            // Check if it's an Excel serial date (numeric value > 40000, which is roughly 2009+)
            elseif (is_numeric($dateValue) && $dateValue > 40000 && $dateValue < 60000) {
                $isDate = true;
            }

            if ($isDate) {
                $date = $this->parseZeoniqDate($dateValue);

                // Use current outlet from "Outlet: XXX" row, or try to find in header
                $outlet = $currentOutlet ?? $this->getColumnValue($row, $headerMap, ['outlet']);
                $grossSales = $this->parseNumber($this->getColumnValue($row, $headerMap, ['gross sales', 'gross amount']));
                $discount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['discount']));
                $netAmountExcl = $this->parseNumber($this->getColumnValue($row, $headerMap, ['net amount excl', 'net amount excl.', 'net sales']));
                $taxAmount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['tax amount incl', 'tax amount incl.', 'tax', 'exclusive tax']));
                $serviceCharges = $this->parseNumber($this->getColumnValue($row, $headerMap, ['exclusive charges', 'charges', 'service charges']));
                $billRounding = $this->parseNumber($this->getColumnValue($row, $headerMap, ['bill rounding', 'rounding']));
                $totalSales = $this->parseNumber($this->getColumnValue($row, $headerMap, ['total sales', 'net total']));
                $transCount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['trans. count', 'transaction count', 'trans count']));
                $guestCount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['guest count', 'pax']));

                // If total_sales not found, calculate it
                if ($totalSales == 0 && $netAmountExcl > 0) {
                    $totalSales = $netAmountExcl + $taxAmount + $serviceCharges + $billRounding;
                }

                // Extract session and department sales data
                $sessions = $this->extractSessionsFromRow($row, $sessionColumnMap);
                $departments = $this->extractDepartmentsFromRow($row, $departmentColumnMap);

                // If session data exists, create separate records per meal period
                if (!empty($sessions)) {
                    // Calculate totals for proportional distribution
                    $totalSessionQty = array_sum(array_column($sessions, 'quantity'));
                    $totalSessionRevenue = array_sum(array_column($sessions, 'net_total'));
                    $sessionCount = count($sessions);

                    // Check if session sum matches the row's Net Total
                    // If there's unassigned revenue, distribute it proportionally across sessions
                    $rowNetTotal = $totalSales > 0 ? $totalSales : $netAmountExcl;
                    $unassignedRevenue = $rowNetTotal - $totalSessionRevenue;

                    if (abs($unassignedRevenue) > 0.01 && $totalSessionRevenue > 0) {
                        // Distribute unassigned revenue proportionally based on each session's share
                        foreach ($sessions as $mealPeriod => $sessionData) {
                            $sessionProportion = $sessionData['net_total'] / $totalSessionRevenue;
                            $adjustment = round($unassignedRevenue * $sessionProportion, 2);
                            $sessions[$mealPeriod]['net_total'] += $adjustment;
                        }
                        // Recalculate total after adjustment
                        $totalSessionRevenue = array_sum(array_column($sessions, 'net_total'));
                    }

                    foreach ($sessions as $session) {
                        // Calculate proportional pax for this session
                        $sessionPax = 0;
                        if ($guestCount > 0) {
                            $sessionPax = $totalSessionQty > 0
                                ? (int) round($guestCount * ($session['quantity'] / $totalSessionQty))
                                : (int) round($guestCount / $sessionCount);
                        }

                        // Distribute department amounts proportionally based on session's share of revenue
                        $sessionDepartments = [];
                        if (!empty($departments) && $totalSessionRevenue > 0) {
                            $sessionProportion = $session['net_total'] / $totalSessionRevenue;
                            foreach ($departments as $deptName => $deptTotal) {
                                $sessionDepartments[$deptName] = round($deptTotal * $sessionProportion, 2);
                            }
                        } elseif (!empty($departments)) {
                            // Equal distribution if no revenue data
                            foreach ($departments as $deptName => $deptTotal) {
                                $sessionDepartments[$deptName] = round($deptTotal / $sessionCount, 2);
                            }
                        }

                        $results[] = [
                            'date' => $date,
                            'outlet_code' => $outlet,
                            'meal_period' => $session['meal_period'],
                            'transactions' => $session['quantity'], // Use session quantity as transactions
                            'pax' => $sessionPax, // Distributed from day-level guest count
                            'gross_revenue' => 0, // Not available per session
                            'discount_amount' => 0, // Not available per session
                            'net_sales' => $session['net_total'],
                            'tax_amount' => 0, // Not available per session
                            'service_charges' => 0, // Not available per session
                            'rounding_amount' => 0, // Not available per session
                            'total_sales' => $session['net_total'],
                            'departments' => $sessionDepartments, // Proportionally distributed
                        ];
                    }
                } else {
                    // Fallback: Create one all_day record if no session data
                    $results[] = [
                        'date' => $date,
                        'outlet_code' => $outlet,
                        'meal_period' => 'all_day',
                        'transactions' => (int) $transCount,
                        'pax' => (int) $guestCount,
                        'gross_revenue' => $grossSales,
                        'discount_amount' => $discount,
                        'net_sales' => $netAmountExcl,
                        'tax_amount' => $taxAmount,
                        'service_charges' => $serviceCharges,
                        'rounding_amount' => $billRounding,
                        'total_sales' => $totalSales,
                        'departments' => $departments,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Detect the type of Zeoniq report based on content.
     */
    public function detectReportType(string $filePath): string
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        foreach ($data as $row) {
            $col0 = strtolower(trim((string) ($row[0] ?? '')));

            if (strpos($col0, 'session sales') !== false) {
                return 'session_sales';
            }
            if (strpos($col0, 'daily') !== false && strpos($col0, 'summary') !== false) {
                return 'daily_summary';
            }
            if (strpos($col0, 'business date') !== false) {
                return 'daily_summary';
            }
        }

        return 'unknown';
    }

    /**
     * Extract unique outlets from the import data.
     */
    public function extractOutlets(array $importData): array
    {
        return collect($importData)
            ->pluck('outlet_code')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Parse Zeoniq date format (M/D/YYYY or Excel serial) to Y-m-d.
     */
    private function parseZeoniqDate($dateValue): string
    {
        // Handle Excel serial date numbers (e.g., 46023)
        if (is_numeric($dateValue) && $dateValue > 40000) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Fall through to string parsing
            }
        }

        // Handle string dates in M/D/YYYY format (US format)
        $dateStr = (string) $dateValue;
        $parts = explode('/', $dateStr);
        if (count($parts) === 3) {
            // Format: M/D/YYYY → parts[0]=month, parts[1]=day, parts[2]=year
            return sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[0], (int) $parts[1]);
        }

        return $dateStr;
    }

    /**
     * Map Zeoniq session names to Servora meal periods.
     */
    private function mapZeoniqSessionToMealPeriod(string $sessionInfo): string
    {
        $sessionLower = strtolower($sessionInfo);

        if (strpos($sessionLower, 'breakfast') !== false) {
            return 'breakfast';
        }
        if (strpos($sessionLower, 'lunch') !== false) {
            return 'lunch';
        }
        if (strpos($sessionLower, 'tea') !== false) {
            return 'tea_time';
        }
        if (strpos($sessionLower, 'dinner') !== false) {
            return 'dinner';
        }
        if (strpos($sessionLower, 'supper') !== false) {
            return 'supper';
        }

        return 'all_day';
    }

    /**
     * Build a date record from session data.
     */
    private function buildDateRecord(string $date, string $outletCode, array $sessionData): array
    {
        return [
            'date' => $date,
            'outlet_code' => $outletCode,
            'sessions' => array_values($sessionData),
        ];
    }

    /**
     * Parse a number from string, handling commas.
     */
    private function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $cleaned = str_replace([',', ' '], '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Get column value by possible header names.
     */
    private function getColumnValue(array $row, array $headerMap, array $possibleNames): mixed
    {
        foreach ($possibleNames as $name) {
            if (isset($headerMap[$name]) && isset($row[$headerMap[$name]])) {
                return $row[$headerMap[$name]];
            }
        }
        return null;
    }

    /**
     * Detect session columns from the session header row.
     * Returns array of [meal_period => [qty_col, total_col], ...]
     */
    private function detectSessionColumns(array $sessionHeaderRow): array
    {
        $sessionMap = [];

        // Session name patterns to look for
        $sessionPatterns = [
            'breakfast' => '/breakfast/i',
            'lunch' => '/lunch/i',
            'tea_time' => '/tea\s*time/i',
            'dinner' => '/dinner/i',
            'supper' => '/supper/i',
        ];

        foreach ($sessionHeaderRow as $colIdx => $cell) {
            $cellValue = trim((string) $cell);
            if (empty($cellValue) || $cellValue === '-') {
                continue;
            }

            foreach ($sessionPatterns as $mealPeriod => $pattern) {
                if (preg_match($pattern, $cellValue)) {
                    // Session column found - quantity is at colIdx, net_total is at colIdx + 1
                    $sessionMap[$mealPeriod] = [$colIdx, $colIdx + 1];
                    break;
                }
            }
        }

        return $sessionMap;
    }

    /**
     * Extract session/meal period sales data from a row.
     * Uses dynamically detected column positions or falls back to defaults.
     */
    private function extractSessionsFromRow(array $row, array $sessionColumnMap = []): array
    {
        $sessions = [];

        // If no dynamic map provided, use default column positions
        // (fallback for backward compatibility)
        if (empty($sessionColumnMap)) {
            $sessionColumnMap = [
                'lunch' => [18, 19],
                'tea_time' => [20, 21],
                'dinner' => [22, 23],
                'breakfast' => [26, 27],
            ];
        }

        foreach ($sessionColumnMap as $mealPeriod => [$qtyCol, $totalCol]) {
            $qty = $this->parseNumber($row[$qtyCol] ?? 0);
            $netTotal = $this->parseNumber($row[$totalCol] ?? 0);

            // Only include sessions with revenue > 0
            if ($netTotal > 0) {
                $sessions[$mealPeriod] = [
                    'meal_period' => $mealPeriod,
                    'quantity' => (int) $qty,
                    'net_total' => $netTotal,
                ];
            }
        }

        return $sessions;
    }

    /**
     * Extract department sales data from a row.
     * Based on the known column positions from DailySummary2026KLCCwithStatsSessionDept.xlsx:
     * AC(28): Dessert Qty, AD(29): Dessert Total
     * AE(30): Add On Qty, AF(31): Add On Total
     * AG(32): Modifier Qty, AH(33): Modifier Total
     * AI(34): Beverage Qty, AJ(35): Beverage Total
     * AK(36): Food Qty, AL(37): Food Total
     * AM(38): Merchandise Qty, AN(39): Merchandise Total
     * AO(40): Open Food Qty, AP(41): Open Food Total
     */
    private function extractDepartmentsFromRow(array $row, array $deptColumnMap = []): array
    {
        $departments = [];

        // Preferred: a dynamically detected [name => net_total_col] map, which
        // is robust to files with a different number of session columns (the
        // Department block shifts left/right depending on how many sessions
        // precede it). Only fall back to fixed column positions when detection
        // failed, to preserve backward compatibility.
        if (empty($deptColumnMap)) {
            $deptColumnMap = [
                'Dessert' => 29,
                'Add On' => 31,
                'Modifier' => 33,
                'Beverage' => 35,
                'Food' => 37,
                'Merchandise' => 39,
                'Open Food' => 41,
            ];
        }

        foreach ($deptColumnMap as $deptName => $totalCol) {
            $netTotal = $this->parseNumber($row[$totalCol] ?? 0);

            // Only include departments with revenue > 0
            if ($netTotal > 0) {
                $departments[$deptName] = $netTotal;
            }
        }

        return $departments;
    }

    /**
     * Dynamically detect department columns from the report header.
     *
     * The header has a "Department" section label (row N) followed by the
     * department names (row N+1), each name sitting above a Quantity / Net Total
     * pair. The Net Total lives in the column immediately after each name.
     * Returns [department_name => net_total_col_index]; empty if not found
     * (callers then fall back to fixed positions).
     */
    private function detectDepartmentColumnMap(array $data): array
    {
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIdx => $cell) {
                if (strcasecmp(trim((string) $cell), 'Department') !== 0) {
                    continue;
                }

                // Department names sit in the row beneath the section label,
                // from this column onward (earlier columns hold session names).
                $namesRow = $data[$rowIndex + 1] ?? [];
                $map = [];
                foreach ($namesRow as $nameCol => $nameCell) {
                    if ($nameCol < $colIdx) {
                        continue;
                    }
                    $name = trim((string) $nameCell);
                    if ($name === '') {
                        continue;
                    }
                    // Quantity is at $nameCol, Net Total at the next column.
                    $map[$name] = $nameCol + 1;
                }

                if (!empty($map)) {
                    return $map;
                }
            }
        }

        return [];
    }

    /**
     * Detect department columns in the Excel data.
     * Returns array of column_index => department_name.
     */
    public function detectDepartmentColumns(array $data): array
    {
        $departments = [];

        // Known standard column headers to exclude from department detection
        $standardColumns = [
            'business date', 'date', 'session', 'outlet', 'subtotal', 'transactions',
            'trans count', 'gross sales', 'gross amount', 'gross', 'discount', 'net sales',
            'net amount', 'net', 'tax', 'tax amount', 'service charge', 'charges', 'rounding',
            'bill rounding', 'total sales', 'total', 'pax', 'guest count', 'covers',
            'order start time', 'order time', 'hour', 'time',
        ];

        // Scan for header row (look for row containing "Business Date" or similar)
        foreach ($data as $rowIndex => $row) {
            $firstCol = strtolower(trim((string) ($row[0] ?? '')));

            // Check if this looks like a header row
            $hasDateHeader = stripos($firstCol, 'business date') !== false || stripos($firstCol, 'date') !== false;

            if ($hasDateHeader || $rowIndex < 20) {
                // Scan this row for potential department columns
                foreach ($row as $colIndex => $cell) {
                    $cellValue = trim((string) $cell);
                    $cellLower = strtolower($cellValue);

                    // Skip empty cells
                    if (empty($cellValue)) {
                        continue;
                    }

                    // Skip standard columns
                    if (in_array($cellLower, $standardColumns)) {
                        continue;
                    }

                    // Skip purely numeric cells
                    if (is_numeric($cellValue)) {
                        continue;
                    }

                    // Potential department names are typically short (1-3 words)
                    $wordCount = str_word_count($cellValue);
                    if ($wordCount > 0 && $wordCount <= 3) {
                        // Check if this looks like a department name
                        // (starts with uppercase, alphanumeric with spaces/hyphens)
                        if (preg_match('/^[A-Z][A-Za-z0-9\s\-&\/]+$/u', $cellValue)) {
                            $departments[$colIndex] = $cellValue;
                        }
                    }
                }

                // If we found potential departments, return them
                if (!empty($departments)) {
                    return $departments;
                }
            }
        }

        return $departments;
    }

    /**
     * Extract unique department names from parsed records.
     */
    public function extractDepartmentNames(array $parsedRecords): array
    {
        $departments = [];

        foreach ($parsedRecords as $record) {
            if (isset($record['departments']) && is_array($record['departments'])) {
                foreach (array_keys($record['departments']) as $dept) {
                    $departments[$dept] = true;
                }
            }

            // For session sales records, check each session
            if (isset($record['sessions']) && is_array($record['sessions'])) {
                foreach ($record['sessions'] as $session) {
                    if (isset($session['departments']) && is_array($session['departments'])) {
                        foreach (array_keys($session['departments']) as $dept) {
                            $departments[$dept] = true;
                        }
                    }
                }
            }
        }

        return array_keys($departments);
    }

    /**
     * Validate that department totals match the expected net sales.
     * Returns array of warnings if variance exceeds 5%.
     */
    public function validateDepartmentTotals(array $parsedRecords): array
    {
        $warnings = [];

        foreach ($parsedRecords as $recordIndex => $record) {
            // For session sales
            if (isset($record['sessions']) && is_array($record['sessions'])) {
                foreach ($record['sessions'] as $sessionIndex => $session) {
                    if (isset($session['departments']) && is_array($session['departments'])) {
                        $deptSum = array_sum($session['departments']);
                        $netSales = $session['net_sales'] ?? $session['total_sales'] ?? 0;

                        if ($netSales > 0) {
                            $variance = abs($deptSum - $netSales);
                            $variancePct = ($variance / $netSales) * 100;

                            if ($variancePct > 5) {
                                $warnings[] = sprintf(
                                    'Record %d, %s: Department total (RM %.2f) differs from Net Sales (RM %.2f) by %.1f%%',
                                    $recordIndex + 1,
                                    $session['meal_period'] ?? 'unknown',
                                    $deptSum,
                                    $netSales,
                                    $variancePct
                                );
                            }
                        }
                    }
                }
            }

            // For daily summary
            if (isset($record['departments']) && is_array($record['departments'])) {
                $deptSum = array_sum($record['departments']);
                $netSales = $record['net_sales'] ?? $record['total_sales'] ?? 0;

                if ($netSales > 0) {
                    $variance = abs($deptSum - $netSales);
                    $variancePct = ($variance / $netSales) * 100;

                    if ($variancePct > 5) {
                        $warnings[] = sprintf(
                            'Record %d (%s): Department total (RM %.2f) differs from Net Sales (RM %.2f) by %.1f%%',
                            $recordIndex + 1,
                            $record['date'] ?? 'unknown',
                            $deptSum,
                            $netSales,
                            $variancePct
                        );
                    }
                }
            }
        }

        return $warnings;
    }
}
