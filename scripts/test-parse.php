<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ZeoniqImportService;

$filePath = __DIR__ . '/../temp.xlsx';

if (!file_exists($filePath)) {
    echo "File not found\n";
    exit(1);
}

$service = new ZeoniqImportService();

echo "Detecting report type...\n";
$type = $service->detectReportType($filePath);
echo "Type: $type\n\n";

echo "Parsing records...\n";
$records = $service->parseDailySummaryExcel($filePath);

echo "Records found: " . count($records) . "\n\n";

if (!empty($records)) {
    echo "First 5 records:\n";
    foreach (array_slice($records, 0, 5) as $idx => $record) {
        echo "\n" . ($idx + 1) . ". Date: {$record['date']}, Outlet: {$record['outlet_code']}, Total: RM " . number_format($record['total_sales'], 2);
        if (!empty($record['departments'])) {
            echo "\n   Departments: " . implode(', ', array_map(fn($k, $v) => "$k: RM" . number_format($v, 2), array_keys($record['departments']), $record['departments']));
        }
        echo "\n";
    }
}
