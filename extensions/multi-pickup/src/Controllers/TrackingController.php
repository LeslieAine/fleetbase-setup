<?php
// extensions/multi-pickup/src/Controllers/TrackingController.php

namespace Extensions\MultiPickup\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Serves the customer-facing tracking page.
 * This is what the SMS link points to.
 *
 * URL: GET /track?order_id=xxx&display_id=1042&merchant=Carlos+Barber
 */
class TrackingController extends Controller
{
    public function show(Request $request)
    {
        $fleetbaseUrl   = rtrim(config('app.url'), '/') . '/api/v1';
        $multiPickupUrl = rtrim(config('app.url'), '/') . '/api/v1/multi-pickup';

        // Load the tracking HTML and inject the API URLs
        $html = file_get_contents(__DIR__ . '/../../tracking.html');
        $html = str_replace('{{FLEETBASE_API_URL}}', $fleetbaseUrl, $html);
        $html = str_replace('{{MULTI_PICKUP_API_URL}}', $multiPickupUrl, $html);

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
