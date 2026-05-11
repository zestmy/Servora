<?php
/**
 * Show department columns from Zeoniq Excel
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = $argv[1] ?? __DIR__ . '/../temp.xlsx';

if (!file_exists($filePath)) {
    echo "File not found: $filePath\n";
    exit(1);
}

$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

echo "Analyzing headers to find department columns...\n";
echo str_repeat('=', 80) . "\n\n";

// Show header rows completely
echo "ROW 7 (Main categories):\n";
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $value = $sheet->getCell($colLetter . '7')->getValue();
    if ($value) {
        echo "  $colLetter: $value\n";
    }
}

echo "\nROW 8 (Column names):\n";
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $value = $sheet->getCell($colLetter . '8')->getValue();
    if ($value) {
        echo "  $colLetter: $value\n";
    }
}

echo "\nROW 9 (Sub-headers):\n";
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $value = $sheet->getCell($colLetter . '9')->getValue();
    if ($value) {
        echo "  $colLetter: $value\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "DEPARTMENT COLUMNS IDENTIFIED:\n";
echo str_repeat('-', 80) . "\n";

// Find where "Department" header starts (row 7)
$deptStartCol = null;
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $value = $sheet->getCell($colLetter . '7')->getValue();
    if (stripos($value, 'department') !== false) {
        $deptStartCol = $col;
        break;
    }
}

if (!$deptStartCol) {
    echo "ERROR: Could not find 'Department' header in row 7\n";
    exit(1);
}

echo "\nDepartment section starts at column " .
    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($deptStartCol) . "\n\n";

// List all departments
$departments = [];
for ($col = $deptStartCol; $col <= $highestColumnIndex; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $deptName = $sheet->getCell($colLetter . '8')->getValue();
    $subHeader = $sheet->getCell($colLetter . '9')->getValue();

    if ($deptName && stripos($subHeader, 'quantity') !== false) {
        // This is a department quantity column
        $netTotalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $departments[$deptName] = [
            'name' => $deptName,
            'quantity_col' => $colLetter,
            'net_total_col' => $netTotalCol,
        ];
        echo "Department: $deptName\n";
        echo "  Quantity Column: $colLetter\n";
        echo "  Net Total Column: $netTotalCol\n\n";
    }
}

echo str_repeat('=', 80) . "\n";
echo "Total departments found: " . count($departments) . "\n\n";

// Show sample data for first date
echo "SAMPLE DATA (Row 11 - First date):\n";
echo str_repeat('-', 80) . "\n";
$date = $sheet->getCell('B11')->getValue();
echo "Business Date: $date\n\n";

foreach ($departments as $dept) {
    $qty = $sheet->getCell($dept['quantity_col'] . '11')->getValue();
    $netTotal = $sheet->getCell($dept['net_total_col'] . '11')->getValue();

    // Parse numbers (remove commas if present)
    $qtyParsed = is_numeric($qty) ? $qty : (float)str_replace(',', '', $qty);
    $netTotalParsed = is_numeric($netTotal) ? $netTotal : (float)str_replace(',', '', $netTotal);

    echo "{$dept['name']}: Qty=$qtyParsed, Net Total=RM " . number_format($netTotalParsed, 2) . "\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Analysis complete!\n";
