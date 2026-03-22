<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Commission
    |--------------------------------------------------------------------------
    |
    | The percentage your platform takes from each delivery.
    | Riders receive (100 - commission)% of the delivery fee.
    |
    | Example: commission = 7, delivery fee = UGX 5,000
    |   Platform earns: UGX 350
    |   Rider earns:    UGX 4,650
    |
    */

    'percentage' => (float) env('PLATFORM_COMMISSION_PERCENTAGE', 7),

    /*
    |--------------------------------------------------------------------------
    | Rider Capacity
    |--------------------------------------------------------------------------
    |
    | Maximum number of packages a single rider can carry simultaneously.
    | When a rider hits this cap, they stop receiving new assignment requests
    | until they complete or drop a delivery.
    |
    */

    'max_packages_per_rider' => (int) env('MAX_PACKAGES_PER_RIDER', 3),

    /*
    |--------------------------------------------------------------------------
    | Nearby Pickup Detection
    |--------------------------------------------------------------------------
    |
    | When a rider is on an active delivery, the system checks if any pending
    | pickups exist within this radius of the rider's current location.
    | If found AND rider has capacity, the rider is notified.
    |
    */

    'nearby_pickup_radius_km' => (float) env('NEARBY_PICKUP_RADIUS_KM', 2),

    /*
    |--------------------------------------------------------------------------
    | Payout Schedule
    |--------------------------------------------------------------------------
    |
    | How often rider earnings are paid out.
    | Options: daily, weekly, biweekly
    |
    | For a small operation starting out, weekly is recommended.
    | Riders expect consistency — pick one and stick to it.
    |
    */

    'payout_schedule' => env('PAYOUT_SCHEDULE', 'weekly'),
    'payout_day'      => env('PAYOUT_DAY', 'friday'), // day of week for weekly payouts

];
