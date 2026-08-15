<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Servora Print Agent — self-hosted PrintNode replacement.
 *
 * See docs/print-agent-plan.md. A print agent is a small program on the
 * outlet PC that pairs with the server, polls for jobs, prints the PDF to a
 * local Windows printer and reports what happened. Same trust model as
 * clock_devices, which this deliberately mirrors: the agent's COMPANY AND
 * OUTLET are facts a manager configured at pairing, not claims the machine
 * makes, and the token is stored hashed with the raw value shown exactly
 * once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // The outlet this agent prints for. Not nullable: an unplaced
            // agent has no printers worth pairing to.
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

            // "Back-office PC". Written by the manager pairing it.
            $table->string('name');

            /*
             * SHA-256 of the bearer token the agent holds. Hashed rather than
             * encrypted: nothing ever needs to read it back, only compare, and
             * a database copy must not be enough to collect another outlet's
             * jobs. Unique so the lookup is one indexed hit.
             */
            $table->string('token_hash', 64)->nullable()->unique();

            // The short code a manager reads out to whoever is at the PC.
            // Consumed on first use — a code that stays valid is a second,
            // weaker credential sitting beside the token.
            $table->string('pairing_code', 12)->nullable()->index();
            $table->dateTime('pairing_expires_at')->nullable();
            $table->dateTime('paired_at')->nullable();

            // The heartbeat. The agent polls every few seconds, so a stale
            // last_seen_at is what turns the printer badge offline.
            $table->dateTime('last_seen_at')->nullable();
            $table->ipAddress('last_seen_ip')->nullable();

            // Reported by the agent at pair and poll, so the UI can nag
            // about stale versions without any update mechanism existing yet.
            $table->string('agent_version', 40)->nullable();
            $table->string('hostname', 120)->nullable();
            $table->string('os', 60)->nullable();

            /*
             * The agent's reported printer inventory:
             * [{name, is_default, status, papers: [{name, size}]}].
             *
             * JSON, not a child table: it is a cache of remote truth,
             * replaced wholesale on every report and never queried
             * relationally — the same shape PrintNodeClient::printers()
             * already returns to the picker.
             */
            $table->json('printers')->nullable();
            $table->dateTime('printers_reported_at')->nullable();

            /*
             * Revoked, not deleted. Jobs point at the agent that carried
             * them, and a stolen PC is precisely when somebody asks which
             * labels went through it.
             */
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // "Which live agents does this outlet have" — the printer form's
            // agent picker and the status badge both ask it.
            $table->index(['company_id', 'outlet_id', 'revoked_at']);
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_printer_id')->nullable()
                ->constrained('label_printers')->nullOnDelete();

            /*
             * The Windows queue name, COPIED from the printer record at
             * submit time. A later edit to the printer record must not
             * retarget a job already in flight.
             */
            $table->string('printer_name', 200);

            // Shown in the agent's local log; identifies the job to a human.
            $table->string('title', 200);

            /*
             * The rendered PDF. In the DB rather than storage/ because a job
             * lives minutes, a blob dies atomically with its row, and the
             * platform already keeps queue/cache/sessions in MySQL. Nulled
             * the moment the job reaches a terminal status — the row skeleton
             * stays for debugging until the pruner removes it.
             */
            $table->binary('payload')->nullable();

            // {paper: string|null, rotate: 0|90}
            $table->json('options')->nullable();

            // pending → delivered → done | error | expired
            $table->string('status', 20)->default('pending');
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('expires_at');
            $table->string('error_message', 500)->nullable();

            $table->timestamps();

            // The poll: "pending jobs for this agent".
            $table->index(['print_agent_id', 'status']);
        });

        /*
         * MEDIUMBLOB, not BLOB: a batch PDF is tens-to-hundreds of KB and
         * BLOB caps at 64 KB, which would truncate silently on some MySQL
         * modes. SQLite (tests) has no column size classes, so skip it there.
         */
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement(
                'ALTER TABLE print_jobs MODIFY payload MEDIUMBLOB NULL'
            );
        }

        Schema::table('label_printers', function (Blueprint $table) {
            // Same semantics as the printnode_* columns beside them: only
            // read when driver = 'agent', cleared when the driver changes.
            $table->foreignId('print_agent_id')->nullable()
                ->after('printnode_printer_id')
                ->constrained('print_agents')->nullOnDelete();
            $table->string('agent_printer_name', 200)->nullable()->after('print_agent_id');
            // Null means "the driver default is already the label stock" —
            // the same deliberate nullability as printnode_paper.
            $table->string('agent_paper', 100)->nullable()->after('agent_printer_name');
        });
    }

    public function down(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_agent_id');
            $table->dropColumn(['agent_printer_name', 'agent_paper']);
        });
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('print_agents');
    }
};
