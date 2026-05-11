<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/../temp.xlsx';
$spreadsheet = IOFactory::load($filePath);
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();

echo "CHECKING ROWS 6-8 FOR HEADERS\n";
echo str_repeat('=', 80) . "\n\n";

for ($rowIdx = 6; $rowIdx <= 8; $rowIdx++) {
    echo "ROW $rowIdx:\n";
    $row = $data[$rowIdx] ?? [];
    foreach ($row as $colIdx => $value) {
        if ($value && trim((string)$value)) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            echo "  [$colIdx] $colLetter: '" . trim((string)$value) . "'\n";
        }
    }
    echo "\n";
}

echo "\nCHECKING FIRST 20 COLUMNS OF ROW 7 (likely sub-headers):\n";
echo str_repeat('-', 80) . "\n";
$row7 = $data[7] ?? [];
for ($i = 0; $i < 20; $i++) {
    $value = $row7[$i] ?? '';
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    echo "$colLetter ($i): '" . trim((string)$value) . "'\n";
}
