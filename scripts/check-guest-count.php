<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ZeoniqImportService;

$filePath = __DIR__ . '/../temp.xlsx';

$service = new ZeoniqImportService();
$records = $service->parseDailySummaryExcel($filePath);

echo "CHECKING EXTRACTED DATA\n";
echo str_repeat('=', 80) . "\n\n";

foreach (array_slice($records, 0, 5) as $idx => $record) {
    echo "Record " . ($idx + 1) . ":\n";
    echo "  Date: {$record['date']}\n";
    echo "  Outlet: {$record['outlet_code']}\n";
    echo "  Meal Period: {$record['meal_period']}\n";
    echo "  Gross Revenue: RM " . number_format($record['gross_revenue'] ?? 0, 2) . "\n";
    echo "  Discount: RM " . number_format($record['discount_amount'] ?? 0, 2) . "\n";
    echo "  Net Sales: RM " . number_format($record['net_sales'] ?? 0, 2) . "\n";
    echo "  Tax: RM " . number_format($record['tax_amount'] ?? 0, 2) . "\n";
    echo "  Service Charges: RM " . number_format($record['service_charges'] ?? 0, 2) . "\n";
    echo "  Total Sales: RM " . number_format($record['total_sales'] ?? 0, 2) . "\n";
    echo "  Transactions: " . ($record['transactions'] ?? 'NOT SET') . "\n";
    echo "  Pax: " . ($record['pax'] ?? 'NOT SET') . "\n";
    echo "\n";
}
