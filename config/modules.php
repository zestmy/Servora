<?php

/*
 * Platform-wide module switches. These turn a whole area of the product on or
 * off for everyone — they are not plan entitlements, which are per company.
 */
return [

    /*
     * Supplier portal and marketplace: supplier logins at /supplier, the
     * public /marketplace and /for-suppliers pages, the in-app "Find
     * Suppliers" directory and supplier product mapping.
     *
     * Parked while Servora focuses on merchants. Switching it off hides the
     * screens and 404s the routes; no code, table or row is removed, so it
     * comes back by flipping this. Supplier records, purchasing, PO email and
     * price alerts are merchant features and do not depend on it.
     */
    'supplier_portal' => (bool) env('SUPPLIER_PORTAL_ENABLED', false),

];
