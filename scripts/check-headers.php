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

echo "FINDING HEADER ROW\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($data as $rowIndex => $row) {
    foreach ($row as $colIdx => $cell) {
        $cellValue = trim((string) $cell);
        if (strcasecmp($cellValue, 'Business Date') === 0) {
            echo "Found header at row $rowIndex\n\n";
            echo "ALL HEADERS IN THIS ROW:\n";
            echo str_repeat('-', 80) . "\n";
            foreach ($row as $idx => $header) {
                $h = trim((string) $header);
                if ($h) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                    echo "[$idx] $colLetter: '$h'\n";
                }
            }
            echo "\n\nFIRST DATA ROW (row " . ($rowIndex + 3) . "):\n";
            echo str_repeat('-', 80) . "\n";
            $dataRow = $data[$rowIndex + 3] ?? [];
            foreach ($dataRow as $idx => $value) {
                if ($value && trim((string)$value)) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                    echo "[$idx] $colLetter: " . var_export($value, true) . "\n";
                }
            }
            exit(0);
        }
    }
}

echo "Header not found!\n";
