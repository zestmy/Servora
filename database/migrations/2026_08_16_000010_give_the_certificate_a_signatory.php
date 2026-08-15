<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who signs the training certificates, per company.
 *
 * Name, designation, the company name AS SIGNED (which is allowed to differ
 * from the operating company — an academy arm or HQ entity often signs), and
 * a signature image. All nullable: an unconfigured company keeps today's
 * certificate, which closes with "Issued by {brand}" and no pen stroke.
 *
 * On companies rather than a settings table because that is where the other
 * certificate branding (logo, brand_name) already lives, and like those it is
 * read LIVE at render time — the PDF is generated from the row on every
 * download, so a signatory change reaches old certificates too, exactly as a
 * logo change always has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'cert_signatory_name')) {
                $table->string('cert_signatory_name')->nullable();
            }
            if (! Schema::hasColumn('companies', 'cert_signatory_title')) {
                $table->string('cert_signatory_title')->nullable();
            }
            if (! Schema::hasColumn('companies', 'cert_signatory_company')) {
                $table->string('cert_signatory_company')->nullable();
            }
            if (! Schema::hasColumn('companies', 'cert_signature_path')) {
                $table->string('cert_signature_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'cert_signatory_name', 'cert_signatory_title',
                'cert_signatory_company', 'cert_signature_path',
            ]);
        });
    }
};
