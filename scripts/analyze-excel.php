<?php
/**
 * Quick Excel file analyzer
 * Run from Windows: php scripts/analyze-excel.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Accept file path from command line or use default
$filePath = $argv[1] ?? 'C:/Users/USER/OneDrive/00 Dottys/Costing/DailySummary2026KLCCwithStatsSessionDept.xlsx';

// Also try without OneDrive path
if (!file_exists($filePath)) {
    $filePath = __DIR__ . '/../temp.xlsx';
}

if (!file_exists($filePath)) {
    echo "File not found: $filePath\n";
    echo "Current directory: " . getcwd() . "\n";
    exit(1);
}

echo "Analyzing: $filePath\n";
echo "File size: " . number_format(filesize($filePath)) . " bytes\n";
echo str_repeat('=', 80) . "\n\n";

// Try to identify the file type
$inputFileType = IOFactory::identify($filePath);
echo "Detected file type: $inputFileType\n\n";

$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

echo "Sheet dimensions: $highestRow rows x $highestColumn columns ($highestColumnIndex)\n\n";

echo "First 30 rows:\n";
echo str_repeat('-', 80) . "\n";

for ($row = 1; $row <= min(30, $highestRow); $row++) {
    echo "Row $row:\n";

    $values = [];
    for ($col = 1; $col <= min(20, $highestColumnIndex); $col++) {
        $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

        if ($cellValue !== null && $cellValue !== '') {
            $values[] = "[$colLetter] = " . substr((string)$cellValue, 0, 50);
        }
    }

    if (!empty($values)) {
        echo "  " . implode(" | ", $values) . "\n";
    }

    echo "\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Analysis complete!\n";
