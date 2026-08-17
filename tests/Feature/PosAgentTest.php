<?php

namespace Tests\Feature;

use App\Jobs\ProcessPosSalesBatch;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PosAgent;
use App\Models\PosSalesBatch;
use App\Scopes\CompanyScope;
use App\Services\Sales\PosAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The POS sync agent's server half: pairing, the ping, the upload. The
 * agent binary is faked with plain HTTP — its whole wire surface is three
 * JSON endpoints. Routes mount at /pos-agent-api in tests (APP_DOMAIN
 * unset), the same environment split as the print agent.
 */
class PosAgentTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private PosAgent $agent;
    private PosAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Sync Co', 'slug' => Str::slug('Sync Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->agent = PosAgent::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Front counter till',
        ]);

        $this->service = app(PosAgentService::class);
    }

    // ── Pairing ─────────────────────────────────────────────────────────

    public function test_pairing_code_redeems_once_for_a_token(): void
    {
        $code = $this->service->issuePairingCode($this->agent);

        $first = $this->postJson('/pos-agent-api/pair', [
            'code' => $code, 'hostname' => 'POS-PC', 'os' => 'windows', 'version' => '0.1.0',
        ]);

        $first->assertOk()->assertJsonPath('status', 'paired');
        $this->assertNotEmpty($first->json('token'));

        $this->agent->refresh();
        $this->assertTrue($this->agent->isPaired());
        $this->assertNull($this->agent->pairing_code);
        $this->assertSame('POS-PC', $this->agent->hostname);

        // The same code again is dead.
        $this->postJson('/pos-agent-api/pair', ['code' => $code])->assertStatus(422);
    }

    public function test_expired_pairing_code_is_refused(): void
    {
        $code = $this->service->issuePairingCode($this->agent);

        $this->agent->forceFill(['pairing_expires_at' => now()->subMinute()])->save();

        $this->postJson('/pos-agent-api/pair', ['code' => $code])->assertStatus(422);
        $this->assertFalse($this->agent->refresh()->isPaired());
    }

    public function test_token_is_stored_hashed_never_plain(): void
    {
        $token = $this->pairedToken();

        $this->assertNotSame($token, $this->agent->refresh()->token_hash);
        $this->assertSame(hash('sha256', $token), $this->agent->token_hash);
    }

    public function test_revoked_agent_is_unpaired_everywhere(): void
    {
        $token = $this->pairedToken();

        $this->service->revoke($this->agent->refresh());

        $this->getJson('/pos-agent-api/ping', [PosAgentService::HEADER => $token])
            ->assertStatus(401)
            ->assertJsonPath('status', 'unpaired');
    }

    // ── Ping ────────────────────────────────────────────────────────────

    public function test_ping_requires_a_token(): void
    {
        $this->getJson('/pos-agent-api/ping')->assertStatus(401);
    }

    public function test_ping_returns_config_and_recent_batches(): void
    {
        $token = $this->pairedToken();

        PosSalesBatch::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'pos_agent_id' => $this->agent->id,
            'outlet_id' => $this->outlet->id, 'source_filename' => 'DailySummary.xlsx',
            'sha256' => str_repeat('a', 64), 'status' => PosSalesBatch::STATUS_APPLIED,
            'result' => ['created' => 2, 'replaced' => 0, 'skipped' => 0],
        ]);

        $this->getJson('/pos-agent-api/ping', [PosAgentService::HEADER => $token])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('agent.current_version', PosAgent::CURRENT_VERSION)
            ->assertJsonPath('config.sync_interval_minutes', PosAgent::DEFAULT_SYNC_INTERVAL_MINUTES)
            ->assertJsonPath('recent_batches.0.status', 'applied')
            ->assertJsonPath('recent_batches.0.result.created', 2);
    }

    // ── Uploads ─────────────────────────────────────────────────────────

    public function test_upload_stages_a_batch_and_queues_processing(): void
    {
        Bus::fake();
        Storage::fake('local');

        $token = $this->pairedToken();

        $response = $this->post('/pos-agent-api/batches', [
            'file'    => UploadedFile::fake()->create('DailySummary.xlsx', 12, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'version' => '0.1.0',
        ], [PosAgentService::HEADER => $token]);

        $response->assertStatus(202)->assertJsonPath('status', 'received');

        $batch = PosSalesBatch::withoutGlobalScope(CompanyScope::class)->sole();
        $this->assertSame($this->company->id, $batch->company_id);
        $this->assertSame($this->outlet->id, $batch->outlet_id);
        $this->assertSame('DailySummary.xlsx', $batch->source_filename);
        $this->assertSame(PosSalesBatch::STATUS_RECEIVED, $batch->status);
        Storage::disk('local')->assertExists($batch->file_path);

        Bus::assertDispatched(ProcessPosSalesBatch::class, fn ($job) => $job->batchId === $batch->id);
    }

    public function test_replaying_the_same_bytes_answers_with_the_original_batch(): void
    {
        Bus::fake();
        Storage::fake('local');

        $token = $this->pairedToken();
        $file = fn () => UploadedFile::fake()->createWithContent('DailySummary.xlsx', 'same-bytes');

        $first = $this->post('/pos-agent-api/batches', ['file' => $file()], [PosAgentService::HEADER => $token]);
        $first->assertStatus(202);

        $second = $this->post('/pos-agent-api/batches', ['file' => $file()], [PosAgentService::HEADER => $token]);
        $second->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('batch_id', $first->json('batch_id'));

        $this->assertSame(1, PosSalesBatch::withoutGlobalScope(CompanyScope::class)->count());
        Bus::assertDispatchedTimes(ProcessPosSalesBatch::class, 1);
    }

    public function test_upload_refuses_non_report_files(): void
    {
        $token = $this->pairedToken();

        $this->post('/pos-agent-api/batches', [
            'file' => UploadedFile::fake()->create('agent.exe', 10, 'application/octet-stream'),
        ], [PosAgentService::HEADER => $token, 'Accept' => 'application/json'])
            ->assertStatus(422);
    }

    // ── Version drift ───────────────────────────────────────────────────

    /**
     * PosAgent::CURRENT_VERSION is the only update mechanism v1 has — the
     * POS Sync screen nags agents reporting something older. It is only
     * honest if it matches what the Go source actually builds, so read the
     * Go source. Bump both together. (PrintAgentTest pins its own pair the
     * same way.)
     */
    public function test_php_and_go_agree_on_the_current_agent_version(): void
    {
        $go = file_get_contents(base_path('tools/pos-agent/main.go'));

        $this->assertNotFalse($go, 'tools/pos-agent/main.go is missing');
        $this->assertMatchesRegularExpression('/const Version = "([^"]+)"/', $go);

        preg_match('/const Version = "([^"]+)"/', $go, $m);

        $this->assertSame(PosAgent::CURRENT_VERSION, $m[1]);
    }

    private function pairedToken(): string
    {
        $code = $this->service->issuePairingCode($this->agent);

        $response = $this->postJson('/pos-agent-api/pair', ['code' => $code]);

        return $response->json('token');
    }
}
