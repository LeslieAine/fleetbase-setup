<?php
// extensions/multi-pickup/src/NearbyPickupScanner.php

namespace Extensions\MultiPickup;

use Extensions\MultiPickup\Models\RiderCapacity;
use Extensions\MultiPickup\Events\NearbyPickupAvailable;
use Illuminate\Support\Facades\Log;

/**
 * NearbyPickupScanner
 *
 * Runs every 2 minutes via Laravel scheduler.
 * Finds active riders with free capacity and checks if
 * any pending pickups exist near their current location.
 *
 * This is the engine behind "a merchant is on your route" notifications.
 *
 * Registered in the Fleetbase service provider's schedule() method:
 *   $schedule->call(fn() => (new NearbyPickupScanner)->run())->everyTwoMinutes();
 */
class NearbyPickupScanner
{
    public function run(): void
    {
        $radiusKm = config('commission.nearby_pickup_radius_km', 2);

        /*
         * Get all online riders who have free capacity.
         * The driver_locations table is maintained by Fleetbase
         * and updated every ~10 seconds as riders move.
         */
        $activeRiders = \DB::select("
            SELECT
                d.uuid AS rider_id,
                d.name,
                d.fcm_token,
                dl.lat,
                dl.lng,
                dl.updated_at AS location_updated_at
            FROM drivers d
            JOIN driver_locations dl ON dl.driver_uuid = d.uuid
            WHERE d.status = 'active'
              AND dl.updated_at > NOW() - INTERVAL 5 MINUTE
        ");

        if (empty($activeRiders)) return;

        foreach ($activeRiders as $rider) {
            // Skip riders at full capacity
            if (!RiderCapacity::hasCapacity($rider->rider_id)) continue;

            $freeSlots = RiderCapacity::freeSlots($rider->rider_id);

            /*
             * Find pending orders within radius of this rider.
             * Haversine formula gives us the great-circle distance.
             */
            $nearbyOrders = \DB::select("
                SELECT
                    o.uuid,
                    o.public_id,
                    wp.lat AS pickup_lat,
                    wp.lng AS pickup_lng,
                    wp.name AS pickup_name,
                    wp.street1 AS pickup_address,
                    (
                        6371 * acos(
                            cos(radians(?)) * cos(radians(wp.lat))
                            * cos(radians(wp.lng) - radians(?))
                            + sin(radians(?)) * sin(radians(wp.lat))
                        )
                    ) AS distance_km
                FROM orders o
                JOIN waypoints wp ON wp.uuid = o.pickup_uuid
                WHERE o.status IN ('created', 'pending')
                  AND o.driver_assigned_uuid IS NULL
                HAVING distance_km <= ?
                ORDER BY distance_km ASC
                LIMIT ?
            ", [
                $rider->lat,
                $rider->lng,
                $rider->lat,
                $radiusKm,
                $freeSlots,
            ]);

            foreach ($nearbyOrders as $order) {
                // Avoid notifying the same rider about the same order repeatedly
                $cacheKey = "notified_nearby:{$rider->rider_id}:{$order->uuid}";
                if (cache()->has($cacheKey)) continue;

                // Mark as notified for 10 minutes to avoid spam
                cache()->put($cacheKey, true, now()->addMinutes(10));

                // Fire event — listener sends FCM notification to rider
                event(new NearbyPickupAvailable(
                    $rider->rider_id,
                    (array) $order,
                    $order->distance_km
                ));
            }
        }

        Log::info('NearbyPickupScanner: scanned ' . count($activeRiders) . ' active riders');
    }
}
