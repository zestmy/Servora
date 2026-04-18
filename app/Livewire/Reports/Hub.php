<?php

namespace App\Livewire\Reports;

use Livewire\Component;

class Hub extends Component
{
    public function render()
    {
        $categories = [
            [
                'title' => 'Purchase',
                'icon'  => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z',
                'reports' => [
                    ['label' => 'Purchase Analysis', 'route' => 'reports.purchase-analysis'],
                    ['label' => 'Purchase Order Summary', 'route' => 'reports.po-summary'],
                    ['label' => 'Price History & Changes', 'route' => 'reports.price-history'],
                ],
            ],
            [
                'title' => 'Order',
                'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                'reports' => [
                    ['label' => 'Order History', 'route' => 'reports.order-history'],
                    ['label' => 'Order Summary Report', 'route' => 'reports.order-summary'],
                    ['label' => 'Order Items By Branch', 'route' => 'reports.order-items-by-branch'],
                    ['label' => 'Delivery Order', 'route' => 'reports.delivery-order'],
                    ['label' => 'Goods Received Note', 'route' => 'reports.grn-report'],
                    ['label' => 'Invoice Summary', 'route' => 'reports.invoice-summary'],
                ],
            ],
            [
                'title' => 'Inventory',
                'icon'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                'reports' => [
                    ['label' => 'Stock Balance Report (Package)', 'route' => 'reports.stock-balance-package'],
                    ['label' => 'Stock Balance Product', 'route' => 'reports.stock-balance-product'],
                    ['label' => 'Stock Card', 'route' => 'reports.stock-card'],
                ],
            ],
            [
                'title' => 'Inventory Action',
                'icon'  => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                'reports' => [
                    ['label' => 'Stock Count', 'route' => 'reports.stock-count'],
                    ['label' => 'Stock Count Analysis Reports', 'route' => 'reports.stock-count-analysis'],
                    ['label' => 'Stock Wastage', 'route' => 'reports.stock-wastage'],
                    ['label' => 'Stock Transfer History', 'route' => 'reports.stock-transfer-history'],
                    ['label' => 'Stock Adjustment', 'route' => 'reports.stock-adjustment'],
                ],
            ],
            [
                'title' => 'Menu',
                'icon'  => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'reports' => [
                    ['label' => 'Cost of Goods Sold (COGS)', 'route' => 'reports.index'],
                    ['label' => 'Sales Menu and Ingredients', 'route' => 'reports.sales-menu-ingredients'],
                    ['label' => 'Menu and Ingredients', 'route' => 'reports.menu-ingredients'],
                ],
            ],
            [
                'title' => 'Kitchen',
                'icon'  => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
                'reports' => [
                    ['label' => 'Production History', 'route' => 'reports.production-history'],
                    ['label' => 'Yield Analysis', 'route' => 'reports.yield-analysis'],
                ],
            ],
            [
                'title' => 'Others',
                'icon'  => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'reports' => [
                    ['label' => 'Inventory Variance', 'route' => 'reports.inventory-variance'],
                ],
            ],
        ];

        return view('livewire.reports.hub', compact('categories'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Reports']);
    }
}
