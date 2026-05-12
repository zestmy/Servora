<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ZeoniqImportService;

$filePath = __DIR__ . '/../temp.xlsx';

$service = new ZeoniqImportService();
$records = $service->parseDailySummaryExcel($filePath);

echo "SESSION EXTRACTION TEST\n";
echo str_repeat('=', 80) . "\n\n";

echo "Total records: " . count($records) . "\n\n";

// Group by date to show sessions per date
$byDate = [];
foreach ($records as $record) {
    $date = $record['date'];
    if (!isset($byDate[$date])) {
        $byDate[$date] = [];
    }
    $byDate[$date][] = $record;
}

echo "First 3 dates with session breakdown:\n";
echo str_repeat('-', 80) . "\n\n";

$count = 0;
foreach ($byDate as $date => $dateRecords) {
    if ($count >= 3) break;

    echo "Date: $date ({$dateRecords[0]['outlet_code']})\n";
    echo "Sessions: " . count($dateRecords) . "\n";

    foreach ($dateRecords as $record) {
        $mealPeriod = ucwords(str_replace('_', ' ', $record['meal_period']));
        echo "  - $mealPeriod: Trans {$record['transactions']}, Net Sales RM " . number_format($record['net_sales'], 2) . "\n";
    }

    $totalNetSales = array_sum(array_column($dateRecords, 'net_sales'));
    echo "  TOTAL: RM " . number_format($totalNetSales, 2) . "\n";
    echo "\n";

    $count++;
}
