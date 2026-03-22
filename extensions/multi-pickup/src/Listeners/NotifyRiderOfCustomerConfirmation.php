<?php
// extensions/multi-pickup/src/Listeners/NotifyRiderOfCustomerConfirmation.php

namespace Extensions\MultiPickup\Listeners;

use Extensions\MultiPickup\Events\CustomerConfirmedReady;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * When a customer confirms they're ready, find the assigned rider
 * and push a notification to their app via FCM.
 */
class NotifyRiderOfCustomerConfirmation
{
    public function handle(CustomerConfirmedReady $event): void
    {
        // Find the rider assigned to this order
        $order = \DB::table('orders')
            ->where('uuid', $event->orderId)
            ->select('driver_assigned_uuid', 'public_id')
            ->first();

        if (!$order || !$order->driver_assigned_uuid) {
            Log::info("CustomerConfirmedReady: no driver assigned for order {$event->orderId}");
            return;
        }

        // Get rider's FCM token
        $driver = \DB::table('drivers')
            ->where('uuid', $order->driver_assigned_uuid)
            ->select('fcm_token', 'name')
            ->first();

        if (!$driver || !$driver->fcm_token) return;

        $message = $event->customerNote
            ? "Customer is ready! Note: {$event->customerNote}"
            : "Customer confirmed they're ready to receive order #{$order->public_id}";

        $this->sendFCM($driver->fcm_token, [
            'title' => '✅ Customer is ready!',
            'body'  => $message,
            'data'  => [
                'type'     => 'customer_confirmed',
                'order_id' => $event->orderId,
            ],
        ]);

        Log::info("Notified rider {$driver->name} that customer is ready for order {$order->public_id}");
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
