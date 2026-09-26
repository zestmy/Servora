<?php

namespace Tests\Feature;

use App\Livewire\Clock\Staff\Punch;
use App\Livewire\Hr\ClockSettings;
use App\Models\ClockDevice;
use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Hr\ClockInException;
use App\Services\Hr\ClockInService;
use App\Services\Hr\KioskQrPolicy;
use App\Services\Hr\KioskQrToken;
use App\Services\Staff\StaffSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Clocking in on your own phone by scanning the rotating QR on the outlet
 * kiosk. See KioskQrToken (the code) and KioskQrPolicy (who must scan it).
 */
class KioskQrClockInTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $otherOutlet;
    private Employee $employee;
    private ClockDevice $kiosk;
    private string $kioskToken = 'kiosk-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'QR Clock Co', 'slug' => Str::slug('QR Clock Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Suria KLCC', 'code' => 'KLCC', 'is_active' => true,
            'latitude' => 3.1579, 'longitude' => 101.7123, 'clock_radius_m' => 150,
        ]);
        $this->otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI City', 'code' => 'IOI', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Aisyah Rahman', 'email' => 'aisyah@example.test', 'is_active' => true,
        ]);

        $this->kiosk = ClockDevice::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Front counter iPad', 'token_hash' => hash('sha256', $this->kioskToken),
            'paired_at' => now(), 'last_seen_at' => now(),
        ]);

        // Face off so the tests are about location; GPS on, which is the
        // default and the thing the QR stands in for.
        ClockSetting::forCompany($this->company->id)->update([
            'require_face' => false, 'require_gps' => true,
            'allow_offsite_with_reason' => false,
            'qr_mode' => ClockSetting::QR_ALLOWED, 'qr_rotate_seconds' => 30,
        ]);
    }

    private function mode(string $mode, array $extra = []): void
    {
        ClockSetting::forCompany($this->company->id)->update(['qr_mode' => $mode] + $extra);
    }

    private function token(?ClockDevice $device = null, ?int $at = null): string
    {
        return app(KioskQrToken::class)->issue($device ?? $this->kiosk, $at)['token'];
    }

    private function punch(array $input = [], string $type = ClockEvent::TYPE_IN): ClockEvent
    {
        return app(ClockInService::class)->punch($this->employee, $type, $input);
    }

    /* ── The code ─────────────────────────────────────────────────────── */

    public function test_a_fresh_code_names_its_kiosk(): void
    {
        $device = app(KioskQrToken::class)->verify($this->token(), $this->employee, $this->outlet);

        $this->assertSame($this->kiosk->id, $device->id);
    }

    public function test_a_code_survives_into_the_next_window_but_not_beyond(): void
    {
        $now = time();

        // Scanned in the last second of a window, submitted after the face.
        app(KioskQrToken::class)->verify($this->token(null, $now - 30), $this->employee, $this->outlet, $now);

        $this->expectException(ClockInException::class);
        $this->expectExceptionMessage('expired');

        // A photo of it sent to somebody at home, opened a minute later.
        app(KioskQrToken::class)->verify($this->token(null, $now - 61), $this->employee, $this->outlet, $now);
    }

    public function test_a_tampered_code_is_refused(): void
    {
        $token = $this->token();
        $forged = preg_replace('/\.(\d+)\./', '.' . ($this->kiosk->id + 1) . '.', $token, 1);

        $this->expectException(ClockInException::class);
        $this->expectExceptionMessage('not a kiosk clock-in code');

        app(KioskQrToken::class)->verify($forged, $this->employee, $this->outlet);
    }

    public function test_the_kiosk_at_another_outlet_does_not_count(): void
    {
        $elsewhere = ClockDevice::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->otherOutlet->id,
            'name' => 'IOI tablet', 'token_hash' => hash('sha256', 'other'),
            'paired_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->expectException(ClockInException::class);
        $this->expectExceptionMessage('another outlet');

        app(KioskQrToken::class)->verify($this->token($elsewhere), $this->employee, $this->outlet);
    }

    public function test_a_revoked_kiosk_no_longer_counts(): void
    {
        $token = $this->token();
        $this->kiosk->update(['revoked_at' => now()]);

        $this->expectException(ClockInException::class);
        $this->expectExceptionMessage('no longer registered');

        app(KioskQrToken::class)->verify($token, $this->employee, $this->outlet);
    }

    /* ── The punch ────────────────────────────────────────────────────── */

    public function test_a_scanned_code_replaces_the_gps_fix(): void
    {
        // No coordinates at all — which, with GPS required, is a refusal
        // for a plain phone punch.
        $event = $this->punch(['qr_token' => $this->token()]);

        $this->assertSame(ClockEvent::SOURCE_QR, $event->source);
        $this->assertSame($this->kiosk->id, (int) $event->clock_device_id);
        $this->assertTrue((bool) $event->within_geofence);
        $this->assertNotContains('no_location', $event->flags ?? []);
        $this->assertStringContainsString('Front counter iPad', $event->locationLabel());
    }

    public function test_a_bad_code_is_refused_rather_than_quietly_falling_back_to_gps(): void
    {
        $this->expectException(ClockInException::class);

        $this->punch([
            'qr_token' => 'k1.1.30.1.nonsense',
            'latitude' => 3.1579, 'longitude' => 101.7123, 'accuracy' => 10,
        ]);
    }

    public function test_required_mode_refuses_a_phone_punch_without_a_code(): void
    {
        $this->mode(ClockSetting::QR_REQUIRED);

        $this->expectException(ClockInException::class);
        $this->expectExceptionMessage('Scan the QR code on the Front counter iPad');

        $this->punch(['latitude' => 3.1579, 'longitude' => 101.7123, 'accuracy' => 10]);
    }

    public function test_required_mode_lets_the_phone_in_when_the_kiosk_is_down(): void
    {
        $this->mode(ClockSetting::QR_REQUIRED);
        $this->kiosk->update(['last_seen_at' => now()->subHour()]);

        $event = $this->punch(['latitude' => 3.1579, 'longitude' => 101.7123, 'accuracy' => 10]);

        $this->assertSame(ClockEvent::SOURCE_BYOD, $event->source, 'A dead tablet must never cost an outlet its attendance.');
    }

    public function test_clock_anywhere_staff_never_need_the_code(): void
    {
        $this->mode(ClockSetting::QR_REQUIRED);
        $this->employee->update(['allow_anywhere' => true]);

        $decision = app(KioskQrPolicy::class)->decide($this->employee->fresh(), $this->outlet);

        $this->assertSame(KioskQrPolicy::NONE, $decision['need']);
    }

    public function test_a_break_without_the_code_is_flagged_not_refused(): void
    {
        $this->punch(['qr_token' => $this->token()]);
        $this->mode(ClockSetting::QR_REQUIRED);

        $event = $this->punch(
            ['latitude' => 3.1579, 'longitude' => 101.7123, 'accuracy' => 10],
            ClockEvent::TYPE_BREAK_START,
        );

        $this->assertContains('no_qr', $event->flags ?? []);
    }

    public function test_off_mode_ignores_a_leftover_code(): void
    {
        $token = $this->token();
        $this->mode(ClockSetting::QR_OFF);

        $event = $this->punch([
            'qr_token' => $token,
            'latitude' => 3.1579, 'longitude' => 101.7123, 'accuracy' => 10,
        ]);

        $this->assertSame(ClockEvent::SOURCE_BYOD, $event->source);
    }

    public function test_an_outlet_that_refuses_phones_takes_one_that_scanned_its_kiosk(): void
    {
        // Phones switched off company-wide: without the QR, "use the tablet".
        $this->mode(ClockSetting::QR_ALLOWED, ['byod_enabled' => false]);
        $this->employee->update(['allow_byod' => false]);

        $decision = app(KioskQrPolicy::class)->decide($this->employee->fresh(), $this->outlet);
        $this->assertSame(KioskQrPolicy::REQUIRED, $decision['need'], 'The code is the phone\'s only way in there.');

        $event = $this->punch(['qr_token' => $this->token()]);
        $this->assertSame(ClockEvent::SOURCE_QR, $event->source);
    }

    /* ── The kiosk ────────────────────────────────────────────────────── */

    public function test_the_kiosk_serves_a_code_that_links_to_the_punch_screen(): void
    {
        // The subdomain middleware puts the company here; the kiosk middleware
        // checks the token against it.
        $response = $this->withSession(['subdomain_company_id' => $this->company->id])
            ->withHeader('X-Kiosk-Token', $this->kioskToken)
            ->postJson(route('clock.kiosk.qr'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('rotate_seconds', 30);

        $this->assertStringStartsWith('data:image/', $response->json('image'));
        $this->assertGreaterThan(0, $response->json('expires_in'));
        $this->assertLessThanOrEqual(30, $response->json('expires_in'));
    }

    public function test_the_kiosk_shows_nothing_when_the_company_has_it_off(): void
    {
        $this->mode(ClockSetting::QR_OFF);

        $this->withSession(['subdomain_company_id' => $this->company->id])
            ->withHeader('X-Kiosk-Token', $this->kioskToken)
            ->postJson(route('clock.kiosk.qr'))
            ->assertOk()
            ->assertJsonPath('enabled', false);
    }

    /* ── The settings screen ──────────────────────────────────────────── */

    private function manager(): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect(['settings.hr', 'hr.clock.manage'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_hr_can_require_the_qr_and_set_how_often_it_changes(): void
    {
        $this->actingAs($this->manager());

        Livewire::test(ClockSettings::class)
            ->assertSet('qr_mode', ClockSetting::QR_ALLOWED)
            ->set('qr_mode', ClockSetting::QR_REQUIRED)
            ->set('qr_rotate_seconds', 20)
            ->call('save')
            ->assertHasNoErrors();

        ClockSetting::forget();
        $settings = ClockSetting::forCompany($this->company->id);
        $this->assertSame(ClockSetting::QR_REQUIRED, $settings->qrMode());
        $this->assertSame(20, $settings->qrRotateSeconds());
    }

    public function test_the_rotation_cannot_be_set_too_short_to_scan(): void
    {
        $this->actingAs($this->manager());

        Livewire::test(ClockSettings::class)
            ->set('qr_rotate_seconds', 3)
            ->call('save')
            ->assertHasErrors(['qr_rotate_seconds']);
    }

    /* ── The staff screen ─────────────────────────────────────────────── */

    public function test_the_punch_screen_asks_for_a_scan_and_carries_a_code_from_the_url(): void
    {
        $this->mode(ClockSetting::QR_REQUIRED);
        session(['subdomain_company_id' => $this->company->id]);
        app(StaffSession::class)->signIn($this->employee, 'email');

        $token = $this->token();

        Livewire::withQueryParams(['kq' => $token])
            ->test(Punch::class)
            ->assertSet('qrToken', $token)
            ->assertSeeHtml('data-need="required"')
            ->call('submit', [])
            ->assertSet('errorMessage', '');

        $this->assertSame(ClockEvent::SOURCE_QR, ClockEvent::sole()->source);
    }
}
