<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LabelPrint;
use App\Models\LabelPrintBatch;
use App\Models\LabelPrinter;
use App\Models\Outlet;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Scopes\CompanyScope;
use App\Services\Labels\AgentDriver;
use App\Services\Labels\AgentJobs;
use App\Services\Labels\PrintAgentService;
use App\Services\Labels\PrinterStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The print agent's server half: pairing, the poll, the ack, and the two
 * sweeps. The agent binary itself is faked with plain HTTP — which is the
 * point: its whole wire surface is four JSON endpoints.
 *
 * Routes mount at /agent-api in tests (APP_DOMAIN unset — the same
 * environment split as the staff apps).
 */
class PrintAgentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private PrintAgent $agent;
    private PrintAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Agent Co', 'slug' => Str::slug('Agent Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->agent = PrintAgent::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Back-office PC',
        ]);

        $this->service = app(PrintAgentService::class);
    }

    // ── Pairing ─────────────────────────────────────────────────────────

    public function test_pairing_code_redeems_once_for_a_token(): void
    {
        $code = $this->service->issuePairingCode($this->agent);

        $first = $this->postJson('/agent-api/pair', [
            'code' => $code, 'hostname' => 'OUTLET-PC', 'os' => 'windows', 'version' => '1.0.0',
        ]);

        $first->assertOk()->assertJsonPath('status', 'paired');
        $this->assertNotEmpty($first->json('token'));

        $this->agent->refresh();
        $this->assertTrue($this->agent->isPaired());
        // Consumed: a code that stays live after use is a second credential.
        $this->assertNull($this->agent->pairing_code);
        $this->assertSame('OUTLET-PC', $this->agent->hostname);

        // The same code again is dead.
        $this->postJson('/agent-api/pair', ['code' => $code])->assertStatus(422);
    }

    public function test_expired_pairing_code_is_refused(): void
    {
        $code = $this->service->issuePairingCode($this->agent);

        $this->agent->forceFill(['pairing_expires_at' => now()->subMinute()])->save();

        $this->postJson('/agent-api/pair', ['code' => $code])->assertStatus(422);
        $this->assertFalse($this->agent->refresh()->isPaired());
    }

    public function test_token_is_stored_hashed_never_plain(): void
    {
        $token = $this->pairedToken();

        $this->assertNotSame($token, $this->agent->refresh()->token_hash);
        $this->assertSame(hash('sha256', $token), $this->agent->token_hash);
    }

    // ── Auth ────────────────────────────────────────────────────────────

    public function test_jobs_endpoint_requires_the_agent_token(): void
    {
        $this->getJson('/agent-api/jobs')
            ->assertStatus(401)
            ->assertJsonPath('status', 'unpaired');

        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => 'nonsense'])
            ->assertStatus(401);

        $token = $this->pairedToken();

        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token])
            ->assertOk()
            ->assertExactJson(['jobs' => []]);
    }

    public function test_a_revoked_agents_token_stops_working_immediately(): void
    {
        $token = $this->pairedToken();

        $this->service->revoke($this->agent->refresh());

        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token])
            ->assertStatus(401)
            ->assertJsonPath('status', 'unpaired');
    }

    public function test_a_token_is_dead_at_another_company(): void
    {
        $token = $this->pairedToken();

        // resolve() is where the subdomain-resolved company meets the
        // token. The wrong company must not resolve, however valid the
        // token itself is.
        $other = Company::create([
            'name' => 'Other Co', 'slug' => 'other-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->assertNull($this->service->resolve($other->id, $token));
        $this->assertNotNull($this->service->resolve($this->company->id, $token));
        $this->assertNotNull($this->service->resolve(null, $token));
    }

    public function test_an_agent_whose_outlet_closed_no_longer_resolves(): void
    {
        $token = $this->pairedToken();

        $this->outlet->update(['is_active' => false]);

        $this->assertNull($this->service->resolve($this->company->id, $token));
    }

    // ── The poll and the ack ────────────────────────────────────────────

    public function test_poll_delivers_pending_jobs_with_payload_and_marks_them(): void
    {
        $token = $this->pairedToken();
        $job   = $this->job(['payload' => '%PDF-fake']);

        $response = $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token]);

        $response->assertOk();
        $this->assertCount(1, $response->json('jobs'));
        $this->assertSame($job->id, $response->json('jobs.0.id'));
        $this->assertSame('DELI DL-888', $response->json('jobs.0.printer_name'));
        $this->assertSame('%PDF-fake', base64_decode($response->json('jobs.0.payload_base64')));

        $job->refresh();
        $this->assertSame('delivered', $job->status);
        $this->assertNotNull($job->claimed_at);

        // A delivered job is not offered again inside the redelivery window.
        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token])
            ->assertOk()->assertExactJson(['jobs' => []]);
    }

    public function test_an_unacked_job_is_offered_again_after_the_redelivery_window(): void
    {
        $token = $this->pairedToken();
        $job   = $this->job();

        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token]);

        // The agent crashed mid-print: no ack ever arrives.
        $job->refresh()->forceFill([
            'claimed_at' => now()->subMinutes(PrintJob::REDELIVER_AFTER_MINUTES + 1),
        ])->save();

        $again = $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token]);
        $this->assertCount(1, $again->json('jobs'));
    }

    public function test_done_ack_settles_the_job_and_its_label_prints(): void
    {
        $token = $this->pairedToken();
        $job   = $this->job();
        [$batch, $print] = $this->batchFor($job);

        $this->postJson("/agent-api/jobs/{$job->id}/status", ['status' => 'done'], ['X-Agent-Token' => $token])
            ->assertOk();

        $job->refresh();
        $this->assertSame('done', $job->status);
        // The blob is the growth risk; it dies the moment the job settles.
        $this->assertNull($job->payload);

        $this->assertSame('done', $print->refresh()->status);
    }

    public function test_error_ack_carries_the_message_and_marks_prints(): void
    {
        $token = $this->pairedToken();
        $job   = $this->job();
        [, $print] = $this->batchFor($job);

        $this->postJson("/agent-api/jobs/{$job->id}/status", [
            'status' => 'error', 'message' => 'SumatraPDF exited 1: printer offline',
        ], ['X-Agent-Token' => $token])->assertOk();

        $this->assertSame('error', $job->refresh()->status);
        $this->assertSame('SumatraPDF exited 1: printer offline', $job->error_message);
        $this->assertSame('error', $print->refresh()->status);
    }

    public function test_a_repeat_ack_does_not_flap_a_settled_job(): void
    {
        $token = $this->pairedToken();
        $job   = $this->job();
        [, $print] = $this->batchFor($job);

        $this->postJson("/agent-api/jobs/{$job->id}/status", ['status' => 'done'], ['X-Agent-Token' => $token]);
        // A crashed agent re-acks after restart, this time as an error.
        // First answer wins; the compliance record must not flap.
        $this->postJson("/agent-api/jobs/{$job->id}/status", ['status' => 'error'], ['X-Agent-Token' => $token]);

        $this->assertSame('done', $job->refresh()->status);
        $this->assertSame('done', $print->refresh()->status);
    }

    public function test_an_agent_cannot_touch_another_agents_job(): void
    {
        $token = $this->pairedToken();

        $otherAgent = PrintAgent::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Other PC',
        ]);
        $foreign = $this->job(['print_agent_id' => $otherAgent->id]);

        // Not in this agent's poll...
        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token])
            ->assertOk()->assertExactJson(['jobs' => []]);

        // ...and not ackable by it either.
        $this->postJson("/agent-api/jobs/{$foreign->id}/status", ['status' => 'done'], ['X-Agent-Token' => $token])
            ->assertStatus(404);

        $this->assertSame('pending', $foreign->refresh()->status);
    }

    // ── Printer inventory ───────────────────────────────────────────────

    public function test_agent_reports_its_printer_inventory(): void
    {
        $token = $this->pairedToken();

        $this->postJson('/agent-api/printers', [
            'printers' => [
                ['name' => 'DELI DL-888', 'is_default' => true, 'status' => 'ok',
                 'papers' => [['name' => '70 x 40 mm', 'size' => '70x40mm']]],
            ],
            'version' => '1.0.1',
        ], ['X-Agent-Token' => $token])->assertOk();

        $this->agent->refresh();
        $reported = $this->agent->reportedPrinters();

        $this->assertCount(1, $reported);
        $this->assertSame('DELI DL-888', $reported[0]['name']);
        $this->assertTrue($reported[0]['is_default']);
        $this->assertSame('70 x 40 mm', $reported[0]['papers'][0]['name']);
        $this->assertSame('1.0.1', $this->agent->agent_version);
        $this->assertNotNull($this->agent->printers_reported_at);
    }

    public function test_malformed_inventory_entries_are_dropped_on_read_not_erroring(): void
    {
        $this->agent->forceFill(['printers' => [
            ['name' => 'GOOD ONE'],
            'not even an array',
            ['no_name_key' => true],
        ]])->save();

        $reported = $this->agent->reportedPrinters();

        $this->assertCount(1, $reported);
        $this->assertSame('GOOD ONE', $reported[0]['name']);
    }

    // ── The driver ──────────────────────────────────────────────────────

    public function test_agent_driver_queues_a_job_and_reports_queued(): void
    {
        $printer = $this->agentPrinter();

        $result = app(AgentDriver::class)->submit([$this->renderableLabel()], $printer);

        $this->assertSame('queued', $result['status']);
        $this->assertNull($result['document']);

        $job = PrintJob::withoutGlobalScope(CompanyScope::class)->findOrFail((int) $result['job_id']);
        $this->assertSame('pending', $job->status);
        $this->assertSame('DELI DL-888', $job->printer_name);
        $this->assertSame('70 x 40 mm', $job->options['paper']);
        $this->assertStringStartsWith('%PDF', (string) $job->payload);
        $this->assertNotNull($job->expires_at);
    }

    public function test_agent_driver_refuses_a_printer_with_no_agent_printer_selected(): void
    {
        $printer = $this->agentPrinter(['agent_printer_name' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no agent printer selected');

        app(AgentDriver::class)->submit([$this->renderableLabel()], $printer);
    }

    public function test_agent_driver_refuses_an_unpaired_agent(): void
    {
        // Order matters: agentPrinter() pairs on demand, so the un-pairing
        // has to come after the printer exists.
        $printer = $this->agentPrinter();
        $this->agent->refresh()->forceFill(['token_hash' => null])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not paired');

        app(AgentDriver::class)->submit([$this->renderableLabel()], $printer);
    }

    // ── Sweeps ──────────────────────────────────────────────────────────

    public function test_overdue_jobs_expire_and_propagate_to_label_prints(): void
    {
        $this->pairedToken();
        $job = $this->job(['expires_at' => now()->subMinute()]);
        [, $print] = $this->batchFor($job);

        $expired = app(AgentJobs::class)->expireOverdue();

        $this->assertSame(1, $expired);
        $job->refresh();
        $this->assertSame('expired', $job->status);
        $this->assertNull($job->payload);
        $this->assertSame('expired', $print->refresh()->status);
    }

    public function test_prune_removes_only_old_settled_jobs(): void
    {
        $this->pairedToken();

        $oldDone   = $this->job(['status' => 'done']);
        $oldDone->forceFill(['created_at' => now()->subDays(PrintJob::KEEP_DAYS + 1)])->save();
        $freshDone = $this->job(['status' => 'done']);
        $oldOpen   = $this->job(['status' => 'pending']);
        $oldOpen->forceFill(['created_at' => now()->subDays(PrintJob::KEEP_DAYS + 1)])->save();

        $deleted = app(AgentJobs::class)->prune();

        $this->assertSame(1, $deleted);
        $remaining = PrintJob::withoutGlobalScope(CompanyScope::class)->pluck('id');
        $this->assertTrue($remaining->contains($freshDone->id));
        // Still pending: expiry's job, not the pruner's — however old.
        $this->assertTrue($remaining->contains($oldOpen->id));
        $this->assertFalse($remaining->contains($oldDone->id));
    }

    // ── Printer status ──────────────────────────────────────────────────

    public function test_printer_status_reads_the_agent_heartbeat(): void
    {
        $this->pairedToken();
        $printer = $this->agentPrinter();

        // Fresh heartbeat, no inventory yet: fail open to online. Fresh
        // PrinterStatus per assertion — it memoises the agent per request.
        $this->agent->refresh()->forceFill(['last_seen_at' => now(), 'printers' => null])->save();
        $this->assertSame(PrinterStatus::ONLINE, app(PrinterStatus::class)->for($printer));

        // Stale heartbeat: the PC is gone.
        $this->agent->forceFill([
            'last_seen_at' => now()->subMinutes(PrintAgent::HEARTBEAT_STALE_MINUTES + 1),
        ])->save();
        $this->assertSame(PrinterStatus::OFFLINE, app(PrinterStatus::class)->for($printer));
    }

    public function test_printer_status_believes_the_agents_own_report(): void
    {
        $this->pairedToken();
        $printer = $this->agentPrinter();

        // Alive, but the queue itself is reported erroring.
        $this->agent->refresh()->forceFill([
            'last_seen_at' => now(),
            'printers'     => [['name' => 'DELI DL-888', 'status' => 'error']],
        ])->save();
        $this->assertSame(PrinterStatus::OFFLINE, app(PrinterStatus::class)->for($printer));

        // Alive, but the queue vanished from the inventory entirely.
        $this->agent->forceFill([
            'printers' => [['name' => 'SOME OTHER PRINTER']],
        ])->save();
        $this->assertSame(PrinterStatus::OFFLINE, app(PrinterStatus::class)->for($printer));
    }

    public function test_printer_status_unknown_when_agent_never_linked(): void
    {
        $printer = $this->agentPrinter(['print_agent_id' => null, 'agent_printer_name' => null]);

        $this->assertSame(PrinterStatus::UNKNOWN, app(PrinterStatus::class)->for($printer));
    }

    // ── The manager screen ──────────────────────────────────────────────

    public function test_agents_screen_renders_and_pairs(): void
    {
        $user = $this->managerUser();

        $this->actingAs($user)->get('/labels/agents')->assertOk();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Labels\Agents::class)
            ->set('name', 'Front counter PC')
            ->set('outlet_id', $this->outlet->id)
            ->call('save')
            ->assertSet('showModal', false)
            // The code modal opens straight off a create — pairing is the
            // only reason the row exists.
            ->assertNotSet('pairingCode', null);

        $created = PrintAgent::withoutGlobalScope(CompanyScope::class)
            ->where('name', 'Front counter PC')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasLivePairingCode());
    }

    public function test_revoking_from_the_screen_kills_the_token(): void
    {
        $token = $this->pairedToken();
        $user  = $this->managerUser();

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Labels\Agents::class)
            ->call('revoke', $this->agent->id);

        $this->assertTrue($this->agent->refresh()->isRevoked());
        $this->getJson('/agent-api/jobs', ['X-Agent-Token' => $token])->assertStatus(401);
    }

    private function managerUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        \Spatie\Permission\Models\Permission::findOrCreate('labels.manage', 'web');
        $user->givePermissionTo('labels.manage');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function pairedToken(): string
    {
        $code = $this->service->issuePairingCode($this->agent->refresh());

        $redeemed = $this->service->redeem($this->company->id, $code);

        return $redeemed['token'];
    }

    private function job(array $overrides = []): PrintJob
    {
        return PrintJob::withoutGlobalScope(CompanyScope::class)->create(array_merge([
            'company_id'     => $this->company->id,
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'DELI DL-888',
            'title'          => 'Servora test — 1 label',
            'payload'        => '%PDF-test',
            'options'        => ['paper' => null],
            'status'         => 'pending',
            'expires_at'     => now()->addMinutes(PrintJob::TTL_MINUTES),
        ], $overrides));
    }

    /**
     * A batch + one pending label print pointing at the given job, the way
     * LabelPrintService wires them: the batch carries driver + job id, the
     * prints carry the driver's status.
     *
     * @return array{0: LabelPrintBatch, 1: LabelPrint}
     */
    private function batchFor(PrintJob $job): array
    {
        $batch = LabelPrintBatch::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'    => $this->company->id,
            'outlet_id'     => $this->outlet->id,
            'printed_at'    => now(),
            'item_count'    => 1,
            'label_count'   => 1,
            'driver'        => 'agent',
            'driver_job_id' => (string) $job->id,
        ]);

        $print = LabelPrint::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'batch_id'    => $batch->id,
            'custom_name' => 'TEST ITEM',
            'label_type'  => 'prep',
            'storage_state' => 'chill',
            'start_at'    => now(),
            'end_at'      => now()->addDay(),
            'copies'      => 1,
            'payload'     => ['values' => []],
            'status'      => 'queued',
        ]);

        return [$batch, $print];
    }

    private function agentPrinter(array $overrides = []): LabelPrinter
    {
        if (! $this->agent->refresh()->isPaired()) {
            $this->pairedToken();
        }

        return LabelPrinter::withoutGlobalScope(CompanyScope::class)->create(array_merge([
            'company_id'         => $this->company->id,
            'outlet_id'          => $this->outlet->id,
            'name'               => 'KLCC DELI',
            'driver'             => 'agent',
            'print_agent_id'     => $this->agent->id,
            'agent_printer_name' => 'DELI DL-888',
            'agent_paper'        => '70 x 40 mm',
            'width_mm'           => 70,
            'height_mm'          => 40,
            'is_active'          => true,
        ], $overrides));
    }

    /**
     * The driver's input shape: a template plus resolved token values —
     * LabelDriver's contract, built by hand the way LabelPrintService does.
     */
    private function renderableLabel(): array
    {
        $template = \App\Models\LabelTemplate::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Test template',
            'label_type' => 'prep',
            'width_mm'   => 70,
            'height_mm'  => 40,
            'layout'     => ['fields' => [
                ['token' => 'item.name', 'x' => 2, 'y' => 2, 'w' => 46, 'h' => 6, 'font_size' => 10],
            ]],
        ]);

        return [
            'template' => $template,
            'values'   => ['item.name' => 'TEST ITEM'],
            'copies'   => 1,
        ];
    }
}
