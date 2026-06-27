<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the RFQ / supplier-quotation tables.
 *
 * The RFQ (Request for Quotation) feature was removed from the procurement
 * module. These tables are no longer referenced by any model or component.
 * Down() is intentionally a no-op — the original create migrations remain in
 * history for anyone who needs to inspect the prior schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('supplier_quotation_lines');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('quotation_request_suppliers');
        Schema::dropIfExists('quotation_request_lines');
        Schema::dropIfExists('quotation_requests');
    }

    public function down(): void
    {
        // No-op: the RFQ feature has been removed and will not be restored.
    }
};
