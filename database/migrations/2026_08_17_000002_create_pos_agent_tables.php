<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The POS sync agent — the print agent's trust model pointed the other way.
 *
 * A small program on the POS terminal pairs with the server exactly like a
 * print agent (manager-issued code, hashed token, one company, one outlet)
 * but the data flows UPSTREAM: the agent uploads the Zeoniq sales report it
 * finds on the machine, and the server parses and applies it through the
 * same code path the manual Excel upload has always used.
 *
 * pos_sales_batches is the staging/audit table between "a file arrived" and
 * "sales records exist": every upload becomes a batch row, a queued job
 * parses and applies it, and anything a human should look at (unmapped
 * departments, conflicts with hand-typed records) parks the batch instead
 * of writing anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // The outlet whose sales this terminal reports. Not nullable: a
            // report has to land somewhere before anyone trusts it.
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

            // "Front counter till". Written by the manager pairing it.
            $table->string('name');

            // SHA-256 of the bearer token — same reasoning as print_agents:
            // compare-only, and a database copy must not become a credential.
            $table->string('token_hash', 64)->nullable()->unique();

            $table->string('pairing_code', 12)->nullable()->index();
            $table->dateTime('pairing_expires_at')->nullable();
            $table->dateTime('paired_at')->nullable();

            $table->dateTime('last_seen_at')->nullable();
            $table->ipAddress('last_seen_ip')->nullable();

            $table->string('agent_version', 40)->nullable();
            $table->string('hostname', 120)->nullable();
            $table->string('os', 60)->nullable();

            // Server-pushed agent settings, adopted on the agent's next ping:
            // {sync_interval_minutes: int}. JSON so v1.x settings need no
            // migration.
            $table->json('config')->nullable();

            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'outlet_id', 'revoked_at']);
        });

        Schema::create('pos_sales_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_agent_id')->constrained()->cascadeOnDelete();

            // The agent's outlet at receipt time, denormalized so the batch
            // list survives an agent being re-pointed later.
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();

            $table->string('source_filename');

            /*
             * SHA-256 of the uploaded bytes, computed server-side. Unique per
             * agent: re-uploading the same export (agent restart, spool
             * replay) answers "duplicate" with the original batch id instead
             * of processing twice.
             */
            $table->char('sha256', 64);

            // Where the raw upload sits on disk (storage/app/private/...).
            // Nulled by the pruner once the retention window passes; the
            // batch row itself stays as the audit trail.
            $table->string('file_path')->nullable();

            // session_sales | daily_summary, from ZeoniqImportService.
            $table->string('report_type', 30)->nullable();

            // The date range the parsed report covered.
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            // received → processing → applied | needs_mapping | needs_review
            //          | failed | superseded
            $table->string('status', 20)->default('received');

            /*
             * What the parser saw: {records, outlets, departments,
             * unmapped_departments, suggestions, warnings}. Cached here so
             * the review screen never re-parses the file.
             */
            $table->json('parsed_summary')->nullable();

            // What applying did: {created, replaced, skipped, errors}.
            $table->json('result')->nullable();

            $table->text('error')->nullable();

            $table->dateTime('processed_at')->nullable();
            $table->dateTime('applied_at')->nullable();
            // Null = applied automatically; set = a person drove the review
            // screen's re-apply.
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['pos_agent_id', 'sha256']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::table('sales_records', function (Blueprint $table) {
            // Which upload wrote this record — the audit link back from a
            // number on the sales screen to the file that carried it.
            $table->foreignId('pos_sales_batch_id')->nullable()->after('source')
                ->constrained('pos_sales_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_sales_batch_id');
        });
        Schema::dropIfExists('pos_sales_batches');
        Schema::dropIfExists('pos_agents');
    }
};
