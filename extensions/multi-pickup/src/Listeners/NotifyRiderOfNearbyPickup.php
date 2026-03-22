<?php
// extensions/multi-pickup/src/Listeners/NotifyRiderOfNearbyPickup.php

namespace Extensions\MultiPickup\Listeners;

use Extensions\MultiPickup\Events\NearbyPickupAvailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * When a nearby pickup is detected near an active rider,
 * push a notification to their app.
 *
 * The rider can then tap "Accept additional pickup" in the app.
 */
class NotifyRiderOfNearbyPickup
{
    public function handle(NearbyPickupAvailable $event): void
    {
        $driver = \DB::table('drivers')
            ->where('uuid', $event->riderId)
            ->select('fcm_token', 'name')
            ->first();

        if (!$driver || !$driver->fcm_token) return;

        $order      = $event->order;
        $distanceKm = round($event->distanceKm, 1);
        $merchantName = $order->pickup_name ?? 'A merchant';

        $this->sendFCM($driver->fcm_token, [
            'title' => "📦 Pickup nearby — {$distanceKm}km away",
            'body'  => "{$merchantName} has a package on your route. Tap to add it.",
            'data'  => [
                'type'       => 'nearby_pickup',
                'order_id'   => $order->uuid,
                'pickup_lat' => $order->pickup_lat,
                'pickup_lng' => $order->pickup_lng,
                'distance'   => $distanceKm,
            ],
        ]);

        Log::info("Notified rider {$event->riderId} of nearby pickup {$order->uuid} ({$distanceKm}km)");
    }

    private function sendFCM(string $token, array $payload): void
    {
        $fcmKey = env('FCM_SERVER_KEY');
        if (!$fcmKey) return;

        Http::withHeaders([
            'Authorization' => "key={$fcmKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $token,
            'notification' => [
                'title' => $payload['title'],
                'body'  => $payload['body'],
                'sound' => 'default',
            ],
            'data' => $payload['data'] ?? [],
        ]);
    }
}
