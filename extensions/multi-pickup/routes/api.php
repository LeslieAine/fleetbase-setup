<?php
// extensions/multi-pickup/routes/api.php

use Illuminate\Support\Facades\Route;
use Extensions\MultiPickup\Controllers\MultiPickupController;
use Extensions\MultiPickup\Controllers\TrackingController;

/*
|--------------------------------------------------------------------------
| Multi-Pickup Extension API Routes
|--------------------------------------------------------------------------
|
| These routes are registered under /api/v1/multi-pickup/
|
| Authentication: Fleetbase API key (Bearer token) required
| except for customer-confirm which uses a signed URL.
|
*/

// Customer tracking page — SMS links point here
// No auth required
Route::get('/track', [TrackingController::class, 'show'])
    ->withoutMiddleware(['auth:api']);

Route::prefix('multi-pickup')->group(function () {

    // ── Rider Capacity ────────────────────────────────────────
    Route::get('/capacity/{riderId}', [MultiPickupController::class, 'getRiderCapacity']);
    Route::post('/capacity/{riderId}/add', [MultiPickupController::class, 'addPackage']);
    Route::post('/capacity/{riderId}/remove', [MultiPickupController::class, 'removePackage']);

    // ── Nearby Pickups ────────────────────────────────────────
    // Called by rider app every 60s when online and has free slots
    Route::get('/nearby-pickups', [MultiPickupController::class, 'getNearbyPickups']);

    // ── Customer Confirmation ─────────────────────────────────
    // No auth — this is called from the tracking page SMS link
    Route::post('/orders/{orderId}/customer-confirm', [MultiPickupController::class, 'customerConfirm'])
        ->withoutMiddleware(['auth:api']);
    Route::get('/orders/{orderId}/customer-confirmed', [MultiPickupController::class, 'isCustomerConfirmed'])
        ->withoutMiddleware(['auth:api']);

    // ── Fleetbase Internal Webhook ────────────────────────────
    // Called when Fleetbase marks an order DELIVERED
    // Register this URL in Fleetbase Console → Settings → Webhooks
    Route::post('/fleetbase-webhook', [MultiPickupController::class, 'fleetbaseWebhook'])
        ->withoutMiddleware(['auth:api']);

});
