<?php
// extensions/multi-pickup/src/Events/NearbyPickupAvailable.php

namespace Extensions\MultiPickup\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the scheduler detects a pending pickup
 * near a rider who has free capacity.
 *
 * This drives the "a merchant is on your path" notification.
 */
class NearbyPickupAvailable
{
    use Dispatchable, SerializesModels;

    public string $riderId;
    public array  $order;
    public float  $distanceKm;

    public function __construct(string $riderId, array $order, float $distanceKm)
    {
        $this->riderId    = $riderId;
        $this->order      = $order;
        $this->distanceKm = $distanceKm;
    }
}
