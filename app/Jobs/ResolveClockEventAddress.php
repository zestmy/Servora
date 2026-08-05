<?php

namespace App\Jobs;

use App\Models\ClockEvent;
use App\Services\Geocoding\ReverseGeocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Resolve the street address for one punch, after the fact.
 *
 * NEVER in the request that records the punch. A clock-in must not wait on a
 * third party, and must certainly not fail because one is down — a member of
 * staff standing at a door at 7am is not going to care whose API is having a
 * morning. The punch is written first; this fills the address in behind it.
 *
 * Rate limited so a busy shift change does not fire fifty requests at
 * Nominatim in a second, which its usage policy asks us not to do and which
 * would get the whole company blocked rather than just one punch delayed.
 */
class ResolveClockEventAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Two attempts, not the default three. The realistic failures are "the
     * provider is down" and "the point has no address" — neither is fixed by
     * a third go, and a missing address is not worth blocking the queue for.
     */
    public $tries = 2;

    public $backoff = 60;

    public function __construct(public int $clockEventId) {}

    public function middleware(): array
    {
        return [new RateLimited('geocoding')];
    }

    public function handle(ReverseGeocoder $geocoder): void
    {
        // No authenticated user on the queue, so the company scope is dropped
        // by hand — the event carries its own company_id.
        $event = ClockEvent::withoutGlobalScopes()->find($this->clockEventId);

        if (! $event || $event->latitude === null || $event->address_resolved_at !== null) {
            return;
        }

        $address = $geocoder->lookup((float) $event->latitude, (float) $event->longitude);

        // Stamped even when nothing came back, so a point with no address is
        // asked about once rather than on every sweep.
        $event->forceFill([
            'address'             => $address,
            'address_resolved_at' => now(),
        ])->save();
    }
}
