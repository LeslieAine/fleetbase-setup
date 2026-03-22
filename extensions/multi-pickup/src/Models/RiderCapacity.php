<?php

namespace Extensions\MultiPickup\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks how many active packages a rider is currently carrying.
 *
 * A rider can carry up to MAX_PACKAGES_PER_RIDER packages simultaneously.
 * This table is the source of truth for that cap.
 *
 * Schema:
 *   rider_id         string  — Fleetbase driver UUID
 *   active_count     int     — current number of packages in transit
 *   order_ids        json    — array of active order IDs rider is carrying
 *   updated_at       timestamp
 */
class RiderCapacity extends Model
{
    protected $table = 'rider_capacities';

    protected $fillable = [
        'rider_id',
        'active_count',
        'order_ids',
    ];

    protected $casts = [
        'order_ids' => 'array',
    ];

    public $timestamps = true;

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Check if a rider has capacity for another package.
     */
    public static function hasCapacity(string $riderId): bool
    {
        $max = config('commission.max_packages_per_rider', 3);
        $record = static::where('rider_id', $riderId)->first();

        if (!$record) {
            return true; // No record means 0 packages
        }

        return $record->active_count < $max;
    }

    /**
     * Add a package to a rider's active count.
     * Returns false if at capacity.
     */
    public static function addPackage(string $riderId, string $orderId): bool
    {
        if (!static::hasCapacity($riderId)) {
            return false;
        }

        $record = static::firstOrCreate(
            ['rider_id' => $riderId],
            ['active_count' => 0, 'order_ids' => []]
        );

        $orderIds = $record->order_ids ?? [];
        $orderIds[] = $orderId;

        $record->update([
            'active_count' => count($orderIds),
            'order_ids' => $orderIds,
        ]);

        return true;
    }

    /**
     * Remove a package from a rider's active count.
     * Called when delivery is completed or cancelled.
     */
    public static function removePackage(string $riderId, string $orderId): void
    {
        $record = static::where('rider_id', $riderId)->first();
        if (!$record) return;

        $orderIds = array_values(
            array_filter($record->order_ids ?? [], fn($id) => $id !== $orderId)
        );

        $record->update([
            'active_count' => count($orderIds),
            'order_ids' => $orderIds,
        ]);
    }

    /**
     * Get how many slots a rider has free.
     */
    public static function freeSlots(string $riderId): int
    {
        $max = config('commission.max_packages_per_rider', 3);
        $record = static::where('rider_id', $riderId)->first();

        if (!$record) return $max;

        return max(0, $max - $record->active_count);
    }
}
