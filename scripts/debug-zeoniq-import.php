<?php
/**
 * Debug Zeoniq Excel Import Issues
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\ZeoniqImportService;

$filePath = $argv[1] ?? __DIR__ . '/../temp.xlsx';

if (!file_exists($filePath)) {
    echo "File not found: $filePath\n";
    exit(1);
}

echo "Analyzing: $filePath\n";
echo str_repeat('=', 80) . "\n\n";

$service = new ZeoniqImportService();
$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

// 1. Detect report type
echo "STEP 1: Detect Report Type\n";
echo str_repeat('-', 80) . "\n";
$reportType = $service->detectReportType($filePath);
echo "Detected Type: $reportType\n\n";

// 2. Show first 20 rows raw
echo "STEP 2: Raw Data (First 20 rows)\n";
echo str_repeat('-', 80) . "\n";
foreach (array_slice($data, 0, 20) as $idx => $row) {
    $col0 = $row[0] ?? '';
    $col1 = $row[1] ?? '';
    $col2 = $row[2] ?? '';

    $col0Type = is_numeric($col0) ? 'NUMERIC' : 'STRING';
    $col1Type = is_numeric($col1) ? 'NUMERIC' : 'STRING';

    if ($col0 || $col1 || $col2) {
        echo "Row $idx:\n";
        echo "  [0] ($col0Type) = " . var_export($col0, true) . "\n";
        if ($col1) echo "  [1] ($col1Type) = " . var_export($col1, true) . "\n";
        if ($col2) echo "  [2] = " . var_export($col2, true) . "\n";
        echo "\n";
    }
}

// 3. Try parsing
echo "\nSTEP 3: Parse Data\n";
echo str_repeat('-', 80) . "\n";

try {
    if ($reportType === 'session_sales') {
        $records = $service->parseSessionSalesExcel($filePath);
    } elseif ($reportType === 'daily_summary') {
        $records = $service->parseDailySummaryExcel($filePath);
    } else {
        echo "ERROR: Unknown report type\n";
        exit(1);
    }

    echo "Records found: " . count($records) . "\n\n";

    if (empty($records)) {
        echo "WARNING: No records parsed!\n";

        // Show header detection
        echo "\nDEBUG: Looking for header row...\n";
        $headerRow = null;
        foreach ($data as $rowIndex => $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            if (stripos($col0, 'Business Date') !== false) {
                echo "Found header at row $rowIndex: '$col0'\n";
                $headerRow = $rowIndex;
                break;
            }
        }

        if ($headerRow === null) {
            echo "ERROR: No header row found!\n";
        } else {
            // Check next few rows after header
            echo "\nDEBUG: Checking rows after header...\n";
            for ($i = $headerRow + 1; $i < min($headerRow + 10, count($data)); $i++) {
                $row = $data[$i];
                $dateValue = $row[0] ?? '';
                $dateType = is_numeric($dateValue) ? 'NUMERIC' : 'STRING';
                echo "Row $i: [$dateType] = " . var_export($dateValue, true);

                // Check if it would match
                $isStringDate = is_string($dateValue) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($dateValue));
                $isSerialDate = is_numeric($dateValue) && $dateValue > 40000 && $dateValue < 60000;

                if ($isStringDate) echo " [MATCH: String Date]";
                if ($isSerialDate) echo " [MATCH: Serial Date]";

                echo "\n";
            }
        }
    } else {
        echo "SUCCESS: Found records\n";
        foreach ($records as $idx => $record) {
            echo "\nRecord " . ($idx + 1) . ":\n";
            echo "  Date: " . ($record['date'] ?? 'N/A') . "\n";
            echo "  Outlet: " . ($record['outlet_code'] ?? 'N/A') . "\n";
            if (isset($record['sessions'])) {
                echo "  Sessions: " . count($record['sessions']) . "\n";
            } else {
                echo "  Total Sales: RM " . number_format($record['total_sales'] ?? 0, 2) . "\n";
            }
            if (!empty($record['departments'])) {
                echo "  Departments: " . implode(', ', array_keys($record['departments'])) . "\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Analysis complete!\n";
