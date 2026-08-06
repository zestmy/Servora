<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A kiosk: one tablet, bolted to one outlet, that anybody may punch on.
 *
 * The point of registering it is that a kiosk's LOCATION is a fact somebody
 * configured rather than a claim a phone makes. A punch arriving on a device
 * in this table is already known to be at the outlet, which is why kiosk
 * punches skip the geofence entirely — no indoor GPS fix, no accuracy
 * tolerance, and nothing a mock-location app can say about it.
 *
 * That only holds if the token cannot be lifted off the tablet and replayed
 * from elsewhere, so the token is stored HASHED and the raw value is shown
 * exactly once, at pairing.
 *
 * Pairing is deliberately in two halves. A manager creates the row — naming
 * the device and choosing its outlet — and gets a short code; the tablet
 * redeems the code for a token. Nobody types an admin password into a device
 * that lives on a counter, and the outlet a kiosk vouches for is set by a
 * person who knows where it is going rather than by whoever unboxes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clock_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // The outlet this device vouches for. Not nullable: an unplaced
            // kiosk has nothing to say about where a punch happened, which is
            // the only reason it exists.
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

            // What it is called on the shelf — "Front counter iPad". Written
            // by the manager pairing it, and the only thing distinguishing two
            // tablets in the same kitchen on the devices screen.
            $table->string('name');

            /*
             * SHA-256 of the bearer token the tablet holds.
             *
             * Hashed rather than encrypted: nothing ever needs to read this
             * back, only to compare against a presented token, and a database
             * copy must not be enough to punch as somebody. Unique so the
             * lookup is a single indexed hit rather than a scan with a
             * hash_equals over every row.
             */
            $table->string('token_hash', 64)->nullable()->unique();

            /*
             * The short code a manager reads out to whoever is holding the
             * tablet. Consumed on first use and cleared — a code that stays
             * valid is a second, weaker credential sitting beside the token.
             */
            $table->string('pairing_code', 12)->nullable()->index();
            $table->dateTime('pairing_expires_at')->nullable();

            $table->dateTime('paired_at')->nullable();

            /*
             * The heartbeat. A kiosk pings while its screen is up, and this is
             * what tells the difference between "everyone should be punching
             * here" and "the tablet has been dead since Tuesday".
             *
             * It is load-bearing rather than cosmetic: when it goes stale,
             * BYOD punches at a kiosk outlet stop being flagged, because the
             * alternative is a dead tablet costing a whole outlet its
             * attendance for the day.
             */
            $table->dateTime('last_seen_at')->nullable();
            $table->ipAddress('last_seen_ip')->nullable();
            $table->string('user_agent', 512)->nullable();

            /*
             * Revoked, not deleted. A punch points at the device that recorded
             * it, and a stolen tablet is precisely when somebody will want to
             * ask which punches came from it — deleting the row would take
             * that answer away at the moment it is needed.
             */
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // "Is there a live kiosk at this outlet right now" — asked on
            // every BYOD punch at a kiosk outlet, so it is indexed.
            $table->index(['company_id', 'outlet_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_devices');
    }
};
