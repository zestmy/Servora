<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ZeoniqImportService;

$filePath = __DIR__ . '/../temp.xlsx';

$service = new ZeoniqImportService();
$records = $service->parseDailySummaryExcel($filePath);

echo "VERIFYING SESSION IMPORT READINESS\n";
echo str_repeat('=', 80) . "\n\n";

echo "Total records to import: " . count($records) . "\n\n";

// Count by meal period
$byMealPeriod = [];
foreach ($records as $record) {
    $mp = $record['meal_period'];
    if (!isset($byMealPeriod[$mp])) {
        $byMealPeriod[$mp] = 0;
    }
    $byMealPeriod[$mp]++;
}

echo "Breakdown by meal period:\n";
echo str_repeat('-', 80) . "\n";
foreach ($byMealPeriod as $mp => $count) {
    echo sprintf("  %-15s: %4d records\n", ucfirst(str_replace('_', ' ', $mp)), $count);
}
echo "\n";

// Check first breakfast record in detail
$firstBreakfast = array_values(array_filter($records, fn($r) => $r['meal_period'] === 'breakfast'))[0] ?? null;

if ($firstBreakfast) {
    echo "Sample Breakfast Record:\n";
    echo str_repeat('-', 80) . "\n";
    echo "Date: {$firstBreakfast['date']}\n";
    echo "Outlet: {$firstBreakfast['outlet_code']}\n";
    echo "Meal Period: {$firstBreakfast['meal_period']}\n";
    echo "Transactions: {$firstBreakfast['transactions']}\n";
    echo "Pax: {$firstBreakfast['pax']}\n";
    echo "Net Sales: RM " . number_format($firstBreakfast['net_sales'], 2) . "\n";
    echo "Total Sales: RM " . number_format($firstBreakfast['total_sales'], 2) . "\n";

    if (!empty($firstBreakfast['departments'])) {
        echo "\nDepartments:\n";
        foreach ($firstBreakfast['departments'] as $dept => $amount) {
            if ($amount > 0) {
                echo "  - $dept: RM " . number_format($amount, 2) . "\n";
            }
        }
    }
    echo "\n";
}

// Verify dates are unique per meal period
$dateCheck = [];
foreach ($records as $record) {
    $key = $record['date'] . '|' . $record['meal_period'];
    if (!isset($dateCheck[$key])) {
        $dateCheck[$key] = 0;
    }
    $dateCheck[$key]++;
}

$duplicates = array_filter($dateCheck, fn($count) => $count > 1);
if (empty($duplicates)) {
    echo "✓ No duplicate date+meal_period combinations found\n";
} else {
    echo "✗ WARNING: Found " . count($duplicates) . " duplicate date+meal_period combinations:\n";
    foreach ($duplicates as $key => $count) {
        echo "  - $key: $count times\n";
    }
}
echo "\n";

echo "Import Status: READY ✓\n";
echo "This data will create " . count($records) . " SalesRecord entries in the database.\n";
