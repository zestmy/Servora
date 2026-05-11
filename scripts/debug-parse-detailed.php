<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/../temp.xlsx';

if (!file_exists($filePath)) {
    echo "File not found\n";
    exit(1);
}

$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

echo "SIMULATING PARSER LOGIC\n";
echo str_repeat('=', 80) . "\n\n";

$headerRow = null;
$dateColumnIndex = 0;
$currentOutlet = null;
$headerMap = [];

foreach ($data as $rowIndex => $row) {
    echo "Row $rowIndex:\n";

    // Find header row
    if ($headerRow === null) {
        foreach ($row as $colIdx => $cell) {
            $cellValue = trim((string) $cell);
            if (stripos($cellValue, 'Business Date') !== false) {
                echo "  FOUND HEADER at row $rowIndex, col $colIdx: '$cellValue'\n";
                $headerRow = $rowIndex;
                $dateColumnIndex = $colIdx;
                foreach ($row as $idx => $header) {
                    $h = strtolower(trim((string) $header));
                    if ($h) {
                        $headerMap[$h] = $idx;
                        echo "    HeaderMap['$h'] = $idx\n";
                    }
                }
                break;
            }
        }
        if ($headerRow !== null) {
            echo "\n";
            continue;
        }
    }

    if ($headerRow === null) {
        echo "  (skipping - no header yet)\n\n";
        continue;
    }

    // Check for outlet row
    $col0 = trim((string) ($row[0] ?? ''));
    if (preg_match('/^Outlet:\s*(.+)/', $col0, $m)) {
        $currentOutlet = trim($m[1]);
        echo "  FOUND OUTLET: '$currentOutlet'\n\n";
        continue;
    }

    // Check for date
    $dateValue = $row[$dateColumnIndex] ?? '';
    echo "  Checking col $dateColumnIndex for date: " . var_export($dateValue, true) . "\n";

    $isDate = false;
    if (is_string($dateValue) && preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($dateValue))) {
        $isDate = true;
        echo "    -> Matched as STRING date\n";
    } elseif (is_numeric($dateValue) && $dateValue > 40000 && $dateValue < 60000) {
        $isDate = true;
        echo "    -> Matched as SERIAL date\n";
    }

    if ($isDate) {
        echo "  FOUND DATE ROW! Outlet: '$currentOutlet'\n";

        // Try to get some sales data
        $grossSales = $row[$headerMap['gross sales'] ?? 999] ?? 'N/A';
        echo "    Gross Sales (from headerMap): $grossSales\n";
    }

    echo "\n";

    if ($rowIndex > 15) {
        echo "...(stopping at row 15)\n";
        break;
    }
}
