<?php

namespace Extensions\MultiPickup\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Extensions\MultiPickup\Models\RiderCapacity;

/**
 * MultiPickupController
 *
 * Handles:
 *  1. Capacity checks before assigning orders to riders
 *  2. Customer "ready to receive" confirmation
 *  3. Nearby pickup detection for riders already on a trip
 *  4. Route-based pickup notifications
 */
class MultiPickupController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // 1. CAPACITY
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/multi-pickup/capacity/{riderId}
     *
     * Check how many free slots a rider has.
     * Called by the rider app before accepting a new order.
     */
    public function getRiderCapacity(string $riderId)
    {
        $max = config('commission.max_packages_per_rider', 3);
        $free = RiderCapacity::freeSlots($riderId);

        return response()->json([
            'rider_id'    => $riderId,
            'max'         => $max,
            'active'      => $max - $free,
            'free_slots'  => $free,
            'has_capacity' => $free > 0,
        ]);
    }

    /**
     * POST /api/v1/multi-pickup/capacity/{riderId}/add
     *
     * Add a package to rider's active list.
     * Called when a rider accepts an order assignment.
     *
     * Body: { "order_id": "ord_..." }
     */
    public function addPackage(Request $request, string $riderId)
    {
        $request->validate(['order_id' => 'required|string']);

        $added = RiderCapacity::addPackage($riderId, $request->order_id);

        if (!$added) {
            $max = config('commission.max_packages_per_rider', 3);
            return response()->json([
                'error' => "Rider is at maximum capacity ({$max} packages)",
                'has_capacity' => false,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'free_slots' => RiderCapacity::freeSlots($riderId),
        ]);
    }

    /**
     * POST /api/v1/multi-pickup/capacity/{riderId}/remove
     *
     * Remove a package from rider's active list.
     * Called when delivery is completed OR cancelled.
     *
     * Body: { "order_id": "ord_..." }
     */
    public function removePackage(Request $request, string $riderId)
    {
        $request->validate(['order_id' => 'required|string']);

        RiderCapacity::removePackage($riderId, $request->order_id);

        return response()->json([
            'ok' => true,
            'free_slots' => RiderCapacity::freeSlots($riderId),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 2. CUSTOMER CONFIRMATION — "Ready to Receive"
    // ─────────────────────────────────────────────────────────

    /**
     * POST /api/v1/multi-pickup/orders/{orderId}/customer-confirm
     *
     * Customer taps "I'm ready to receive" in their tracking link.
     * This signals the rider that the customer is home/available.
     *
     * Flow:
     *   Customer gets SMS/WhatsApp with tracking link
     *   → Link shows map + "Confirm I'm ready" button
     *   → Button calls this endpoint
     *   → Rider app shows notification "Customer confirmed, ready to receive"
     *   → Rider proceeds to dropoff
     *
     * Body: { "order_id": "ord_...", "customer_note": "optional note" }
     */
    public function customerConfirm(Request $request, string $orderId)
    {
        $request->validate([
            'order_id'      => 'required|string',
            'customer_note' => 'nullable|string|max:200',
        ]);

        // Store confirmation in cache (fast, no DB hit needed)
        $key = "customer_confirmed:{$orderId}";
        cache()->put($key, [
            'confirmed_at'  => now()->toIso8601String(),
            'customer_note' => $request->customer_note,
        ], now()->addHours(6));

        // Fire event — the listener will notify the rider via FCM/socket
        event(new \Extensions\MultiPickup\Events\CustomerConfirmedReady(
            $orderId,
            $request->customer_note
        ));

        return response()->json([
            'ok'      => true,
            'message' => 'Great! Your rider has been notified you\'re ready.',
        ]);
    }

    /**
     * GET /api/v1/multi-pickup/orders/{orderId}/customer-confirmed
     *
     * Check if customer has confirmed for a given order.
     * Polled by rider app to show confirmation status.
     */
    public function isCustomerConfirmed(string $orderId)
    {
        $key = "customer_confirmed:{$orderId}";
        $data = cache()->get($key);

        return response()->json([
            'confirmed'    => !is_null($data),
            'confirmed_at' => $data['confirmed_at'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 3. NEARBY PICKUPS
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/multi-pickup/nearby-pickups
     *
     * Find pending pickup orders near a rider's current location.
     * Called periodically by the rider app when they have free capacity.
     *
     * Query params:
     *   rider_id  — the rider's ID
     *   lat       — rider's current latitude
     *   lng       — rider's current longitude
     *
     * Returns list of nearby pending orders the rider could pick up.
     */
    public function getNearbyPickups(Request $request)
    {
        $request->validate([
            'rider_id' => 'required|string',
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
        ]);

        $riderId = $request->rider_id;
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radiusKm = config('commission.nearby_pickup_radius_km', 2);

        // Check rider has capacity
        if (!RiderCapacity::hasCapacity($riderId)) {
            return response()->json([
                'nearby_pickups' => [],
                'message' => 'Rider at full capacity',
            ]);
        }

        $freeSlots = RiderCapacity::freeSlots($riderId);

        /*
         * Haversine formula in raw SQL to find pending orders
         * within $radiusKm of rider's current position.
         *
         * This queries the Fleetbase orders table for orders that:
         *  - Have status = 'pending' or 'created'
         *  - Have no assigned driver yet
         *  - Have pickup coordinates within radius
         *
         * NOTE: Table/column names here match Fleetbase's schema.
         * If Fleetbase updates their schema, adjust accordingly.
         */
        $nearbyOrders = \DB::select("
            SELECT
                o.uuid,
                o.public_id,
                o.status,
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
        ", [$lat, $lng, $lat, $radiusKm, $freeSlots]);

        return response()->json([
            'rider_id'       => $riderId,
            'free_slots'     => $freeSlots,
            'radius_km'      => $radiusKm,
            'nearby_pickups' => $nearbyOrders,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 4. MEDUSA WEBHOOK — mark order delivered
    // ─────────────────────────────────────────────────────────

    /**
     * Called internally when Fleetbase marks an order DELIVERED.
     * Notifies your Medusa backend to mark the order fulfilled.
     *
     * This is triggered by the Fleetbase order.completed webhook
     * which you register at: Settings → Webhooks in Fleetbase console
     *
     * Webhook URL: http://localhost:8000/api/v1/multi-pickup/fleetbase-webhook
     */
    public function fleetbaseWebhook(Request $request)
    {
        $event = $request->input('event');
        $data  = $request->input('data', []);

        // Verify webhook secret
        $secret = $request->header('X-Webhook-Secret');
        if ($secret !== config('app.key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($event === 'order.completed') {
            $fulfillmentId = $data['meta']['fulfillment_id'] ?? null;
            $orderId       = $data['meta']['medusa_order_id'] ?? null;

            if ($fulfillmentId && $orderId) {
                $this->notifyMedusaDelivered($orderId, $fulfillmentId);

                // Free up rider capacity
                $riderId = $data['driver']['uuid'] ?? null;
                if ($riderId) {
                    RiderCapacity::removePackage($riderId, $fulfillmentId);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Call Medusa backend to mark fulfillment as delivered.
     * This triggers payment capture automatically.
     */
    private function notifyMedusaDelivered(string $orderId, string $fulfillmentId): void
    {
        $medusaUrl = config('app.medusa_backend_url', env('MEDUSA_BACKEND_URL'));
        $secret    = env('MEDUSA_WEBHOOK_SECRET');

        Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Webhook-Secret' => $secret,
        ])->post("{$medusaUrl}/courier/delivery-confirmed", [
            'order_id'       => $orderId,
            'fulfillment_id' => $fulfillmentId,
            'source'         => 'fleetbase',
            'delivered_at'   => now()->toIso8601String(),
        ]);
    }
}
