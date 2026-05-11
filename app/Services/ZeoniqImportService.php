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

        $results = [];
        $headerRow = null;
        $headerMap = [];

        foreach ($data as $rowIndex => $row) {
            $col0 = trim((string) ($row[0] ?? ''));

            // Find header row (contains "Business Date")
            if ($headerRow === null && stripos($col0, 'Business Date') !== false) {
                $headerRow = $rowIndex;
                foreach ($row as $idx => $header) {
                    $headerMap[strtolower(trim((string) $header))] = $idx;
                }
                continue;
            }

            // Skip if no headers found yet
            if ($headerRow === null) continue;

            // Try to parse date from first column
            $dateStr = $col0;
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateStr)) {
                $date = $this->parseZeoniqDate($dateStr);

                $outlet = $this->getColumnValue($row, $headerMap, ['outlet']);
                $grossSales = $this->parseNumber($this->getColumnValue($row, $headerMap, ['gross sales', 'gross amount']));
                $discount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['discount']));
                $netAmountExcl = $this->parseNumber($this->getColumnValue($row, $headerMap, ['net amount excl', 'net amount excl.', 'net sales']));
                $taxAmount = $this->parseNumber($this->getColumnValue($row, $headerMap, ['tax amount incl', 'tax amount incl.', 'tax', 'exclusive tax']));
                $serviceCharges = $this->parseNumber($this->getColumnValue($row, $headerMap, ['exclusive charges', 'charges', 'service charges']));
                $billRounding = $this->parseNumber($this->getColumnValue($row, $headerMap, ['bill rounding', 'rounding']));
                $totalSales = $this->parseNumber($this->getColumnValue($row, $headerMap, ['total sales', 'net total']));

                // If total_sales not found, calculate it
                if ($totalSales == 0 && $netAmountExcl > 0) {
                    $totalSales = $netAmountExcl + $taxAmount + $serviceCharges + $billRounding;
                }

                $results[] = [
                    'date' => $date,
                    'outlet_code' => $outlet,
                    'meal_period' => 'all_day',
                    'gross_revenue' => $grossSales,
                    'discount_amount' => $discount,
                    'net_sales' => $netAmountExcl,
                    'tax_amount' => $taxAmount,
                    'service_charges' => $serviceCharges,
                    'rounding_amount' => $billRounding,
                    'total_sales' => $totalSales,
                ];
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
     * Parse Zeoniq date format (D/M/YYYY) to Y-m-d.
     */
    private function parseZeoniqDate(string $dateStr): string
    {
        $parts = explode('/', $dateStr);
        if (count($parts) === 3) {
            return sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
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
