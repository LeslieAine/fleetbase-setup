<?php
// extensions/multi-pickup/MultiPickupServiceProvider.php

namespace Extensions\MultiPickup;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Extensions\MultiPickup\Events\CustomerConfirmedReady;
use Extensions\MultiPickup\Events\NearbyPickupAvailable;
use Extensions\MultiPickup\Listeners\NotifyRiderOfCustomerConfirmation;
use Extensions\MultiPickup\Listeners\NotifyRiderOfNearbyPickup;

class MultiPickupServiceProvider extends ServiceProvider
{
    /**
     * Register the extension routes, migrations,
     * event listeners, and scheduled tasks.
     */
    public function boot(): void
    {
        // ── Routes ────────────────────────────────────────────
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // ── Migrations ────────────────────────────────────────
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // ── Events ────────────────────────────────────────────
        $this->app['events']->listen(
            CustomerConfirmedReady::class,
            NotifyRiderOfCustomerConfirmation::class
        );

        $this->app['events']->listen(
            NearbyPickupAvailable::class,
            NotifyRiderOfNearbyPickup::class
        );

        // ── Scheduler ─────────────────────────────────────────
        // Scan for nearby pickups every 2 minutes
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn() => (new NearbyPickupScanner)->run())
                     ->everyTwoMinutes()
                     ->name('nearby-pickup-scan')
                     ->withoutOverlapping();
        });

        // ── Config ────────────────────────────────────────────
        $this->mergeConfigFrom(__DIR__ . '/../../config/commission.php', 'commission');
    }

    public function register(): void
    {
        //
    }
}
