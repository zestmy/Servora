<?php
/**
 * Excel analyzer with multiple reader attempts
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = $argv[1] ?? __DIR__ . '/../temp.xlsx';

if (!file_exists($filePath)) {
    echo "File not found: $filePath\n";
    echo "\nUsage: php scripts/analyze-excel-v2.php <path-to-excel-file>\n";
    echo "   OR: Copy file to servora/temp.xlsx and run without arguments\n";
    exit(1);
}

echo "File: $filePath\n";
echo "Size: " . number_format(filesize($filePath)) . " bytes\n";
echo str_repeat('=', 80) . "\n\n";

// Try different readers
$readers = ['Xlsx', 'Xls', 'Csv'];
$spreadsheet = null;

foreach ($readers as $readerType) {
    try {
        echo "Trying $readerType reader... ";
        $reader = IOFactory::createReader($readerType);
        $spreadsheet = $reader->load($filePath);
        echo "SUCCESS!\n\n";
        break;
    } catch (\Exception $e) {
        echo "failed\n";
    }
}

if (!$spreadsheet) {
    echo "\nERROR: Could not read file with any reader.\n";
    echo "The file might be:\n";
    echo "  - A OneDrive placeholder (not fully downloaded)\n";
    echo "  - Corrupted\n";
    echo "  - In an unsupported format\n\n";
    echo "Try:\n";
    echo "  1. Right-click the file in OneDrive → 'Always keep on this device'\n";
    echo "  2. Open the file in Excel and Save As to a local drive\n";
    echo "  3. Copy to C:\\WebDev\\servora\\temp.xlsx\n";
    exit(1);
}

$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

echo "Sheet: {$sheet->getTitle()}\n";
echo "Dimensions: $highestRow rows × $highestColumn columns\n";
echo str_repeat('-', 80) . "\n\n";

// Show first 50 rows with better formatting
for ($row = 1; $row <= min(50, $highestRow); $row++) {
    $hasData = false;
    $rowData = [];

    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $value = $sheet->getCell($colLetter . $row)->getValue();

        if ($value !== null && $value !== '') {
            $hasData = true;
            $displayValue = is_string($value) ? substr($value, 0, 40) : $value;
            $rowData[] = sprintf("%-3s: %s", $colLetter, $displayValue);
        }
    }

    if ($hasData) {
        echo "Row $row:\n";
        foreach ($rowData as $data) {
            echo "  $data\n";
        }
        echo "\n";
    }
}

echo str_repeat('=', 80) . "\n";
echo "Analysis complete!\n";
