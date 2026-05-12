<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/../temp.xlsx';
$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

echo "CHECKING SESSION/MEAL PERIOD DATA\n";
echo str_repeat('=', 80) . "\n\n";

// Session columns based on headers
$sessions = [
    ['name' => 'Breakfast', 'qty_col' => 26, 'total_col' => 27],  // AA-AB
    ['name' => 'Lunch', 'qty_col' => 18, 'total_col' => 19],      // S-T
    ['name' => 'TeaTime', 'qty_col' => 20, 'total_col' => 21],    // U-V
    ['name' => 'Dinner', 'qty_col' => 22, 'total_col' => 23],     // W-X
];

// Check first data row (after outlet row)
echo "First data row (row 10):\n";
echo str_repeat('-', 80) . "\n";
$dataRow = $data[10] ?? [];

echo "Date: " . ($dataRow[1] ?? 'N/A') . "\n\n";

foreach ($sessions as $session) {
    $qty = $dataRow[$session['qty_col']] ?? 0;
    $total = $dataRow[$session['total_col']] ?? 0;

    $colLetter1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($session['qty_col'] + 1);
    $colLetter2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($session['total_col'] + 1);

    echo "{$session['name']}:\n";
    echo "  Quantity ($colLetter1): " . var_export($qty, true) . "\n";
    echo "  Net Total ($colLetter2): " . var_export($total, true) . "\n";

    if (is_numeric($total) && $total > 0) {
        echo "  Formatted: Qty = " . number_format($qty) . ", Total = RM " . number_format($total, 2) . "\n";
    }
    echo "\n";
}
