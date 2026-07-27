<?php

namespace App\Helpers;

/**
 * One source of truth for friendly purchasing status labels. The list
 * badges and the filter dropdowns must always use the same wording — a
 * record must never read "Submitted" as a badge while filtering under
 * "Pending Approval".
 */
class PurchasingStatus
{
    public const PR = [
        'draft'     => 'Draft',
        'submitted' => 'Pending Approval',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'converted' => 'Converted to PO',
        'cancelled' => 'Cancelled',
    ];

    public const PO = [
        'draft'     => 'Draft',
        'submitted' => 'Pending Approval',
        'approved'  => 'Approved',
        'sent'      => 'Processing',
        'partial'   => 'Partially Received',
        'received'  => 'Received',
        'cancelled' => 'Cancelled',
    ];

    public static function pr(string $status): string
    {
        return self::PR[$status] ?? ucfirst($status);
    }

    public static function po(string $status): string
    {
        return self::PO[$status] ?? ucfirst($status);
    }
}
