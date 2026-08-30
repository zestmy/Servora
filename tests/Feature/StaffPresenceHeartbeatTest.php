<?php

namespace Tests\Feature;

use App\Models\ClockSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Services\Hr\PresenceHeartbeat;
use App\Services\Staff\StaffSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Last seen" — the Staff Portal reporting that it is open, and where.
 *
 * What these pin is mostly what the feature REFUSES to record, because that
 * is where its honesty lives. A column reading "At Bangsar" when the phone
 * last spoke four hours ago, or when the fix was two kilometres wide, is
 * worse than an empty column: somebody acts on it.
 */
class StaffPresenceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    /** A fix a few metres from the outlet door. */
    private const AT_OUTLET = ['latitude' => 3.1291, 'longitude' => 101.6791, 'accuracy' => 20];

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Heartbeat Co',
            'slug'      => Str::slug('Heartbeat Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        // Coordinates on the outlet, so a distance has something to measure to.
        $this->outlet = Outlet::create([
            'company_id'     => $this->company->id,
            'name'           => 'Bangsar',
            'code'           => 'BSR',
            'is_active'      => true,
            'latitude'       => 3.1290,
            'longitude'      => 101.6790,
            'clock_radius_m' => 150,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Nurul Huda Binti Ismail',
            'email'      => 'huda' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        // A PIN session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $this->company->id]);
        app(StaffSession::class)->signIn($this->employee, 'email');
    }

    private function enableLocation(): void
    {
        ClockSetting::forCompany($this->company->id)->update(['location_heartbeat' => true]);
    }

    private function beat(array $input = []): void
    {
        app(PresenceHeartbeat::class)->record($this->employee, $input);
    }

    public function test_a_ping_records_the_time_even_with_no_location_at_all(): void
    {
        $this->beat();

        $this->employee->refresh();

        // The timestamp is the half that is always collected: it needs no
        // permission, no coordinates and no company opt-in.
        $this->assertNotNull($this->employee->last_seen_at);
        $this->assertNull($this->employee->last_seen_latitude);
    }

    public function test_coordinates_are_ignored_until_the_company_switches_them_on(): void
    {
        $this->beat(self::AT_OUTLET);

        $this->employee->refresh();

        $this->assertNotNull($this->employee->last_seen_at, 'The time is still recorded.');
        $this->assertNull($this->employee->last_seen_latitude, 'The location is not.');
    }

    public function test_coordinates_are_recorded_once_the_company_switches_them_on(): void
    {
        $this->enableLocation();

        $this->beat(self::AT_OUTLET);

        $this->employee->refresh();

        $this->assertEqualsWithDelta(3.1291, (float) $this->employee->last_seen_latitude, 0.00001);
        $this->assertSame(20, $this->employee->last_seen_accuracy_m);
        $this->assertSame('At Bangsar', $this->employee->lastSeenPlaceLabel());
    }

    public function test_a_fix_too_vague_to_mean_anything_is_dropped(): void
    {
        $this->enableLocation();

        // 3km of claimed error. Rendering that as a place would put somebody in
        // a different neighbourhood on the word of a cell tower.
        $this->beat(['latitude' => 3.1291, 'longitude' => 101.6791, 'accuracy' => 3000]);

        $this->employee->refresh();

        $this->assertNotNull($this->employee->last_seen_at);
        $this->assertNull($this->employee->last_seen_latitude);
    }

    public function test_coordinates_without_an_accuracy_are_refused(): void
    {
        $this->enableLocation();

        // No browser omits coords.accuracy, so this shape did not come from the
        // Geolocation API — it came from somebody posting by hand.
        $this->beat(['latitude' => 3.1291, 'longitude' => 101.6791]);

        $this->employee->refresh();

        $this->assertNull($this->employee->last_seen_latitude);
    }

    public function test_a_ping_without_a_fix_clears_the_last_one_rather_than_leaving_it_standing(): void
    {
        $this->enableLocation();

        $this->beat(self::AT_OUTLET);
        $this->assertNotNull($this->employee->fresh()->last_seen_latitude);

        // Permission revoked at lunchtime; the app is still being opened.
        $this->travel(2)->minutes();
        $this->beat();

        $this->employee->refresh();

        $this->assertNull(
            $this->employee->last_seen_latitude,
            'This morning coordinates must not be shown under a fresh timestamp.',
        );
        $this->assertNotNull($this->employee->last_seen_at);
    }

    public function test_the_row_is_not_rewritten_within_a_minute(): void
    {
        $this->enableLocation();

        $this->beat(self::AT_OUTLET);
        $first = $this->employee->fresh()->last_seen_at;

        $this->travel(20)->seconds();
        $this->beat(self::AT_OUTLET);

        $this->assertTrue($first->equalTo($this->employee->fresh()->last_seen_at));

        $this->travel(2)->minutes();
        $this->beat(self::AT_OUTLET);

        $this->assertTrue($this->employee->fresh()->last_seen_at->gt($first));
    }

    public function test_a_stale_ping_keeps_its_time_but_stops_claiming_a_place(): void
    {
        $this->enableLocation();

        $this->beat(self::AT_OUTLET);

        $this->travel(Employee::LAST_SEEN_FRESH_MINUTES + 5)->minutes();

        $this->employee->refresh();

        $this->assertNotNull($this->employee->lastSeenLabel(), 'When is still worth knowing.');
        $this->assertNull($this->employee->lastSeenPlaceLabel(), 'Where has stopped being true.');
        $this->assertFalse($this->employee->lastSeenIsFresh());
    }

    public function test_somewhere_else_is_reported_as_a_distance_from_the_outlet(): void
    {
        $this->enableLocation();

        // Roughly 1.1km north of the outlet.
        $this->beat(['latitude' => 3.1390, 'longitude' => 101.6790, 'accuracy' => 25]);

        $this->employee->refresh();

        $this->assertStringContainsString('from Bangsar', (string) $this->employee->lastSeenPlaceLabel());
    }

    public function test_switching_the_setting_off_erases_the_locations_but_keeps_the_times(): void
    {
        $this->enableLocation();

        $this->beat(self::AT_OUTLET);

        PresenceHeartbeat::forget($this->company->id);

        $this->employee->refresh();

        $this->assertNull($this->employee->last_seen_latitude);
        $this->assertNull($this->employee->last_seen_accuracy_m);
        $this->assertNotNull($this->employee->last_seen_at);
    }

    public function test_the_endpoint_writes_the_signed_in_employee_and_never_one_named_in_the_payload(): void
    {
        $this->enableLocation();

        $colleague = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Somebody Else',
            'email'      => 'else' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        $this->postJson(route('clock.staff.heartbeat'), [
            'employee_id' => $colleague->id,
            'latitude'    => 3.1291,
            'longitude'   => 101.6791,
            'accuracy'    => 20,
        ])->assertNoContent();

        $this->assertNotNull($this->employee->fresh()->last_seen_at);
        $this->assertNull(
            $colleague->fresh()->last_seen_at,
            'An employee id in the payload must not steer the write.',
        );
    }

    public function test_the_endpoint_is_behind_the_pin_gate(): void
    {
        app(StaffSession::class)->signOut();

        $this->post(route('clock.staff.heartbeat'), [])
            ->assertRedirect(route('clock.staff.login'));
    }
}
