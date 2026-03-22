<?php
// extensions/multi-pickup/src/Events/CustomerConfirmedReady.php

namespace Extensions\MultiPickup\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer taps "I'm ready to receive" on their tracking page.
 * The listener picks this up and notifies the assigned rider.
 */
class CustomerConfirmedReady
{
    use Dispatchable, SerializesModels;

    public string $orderId;
    public ?string $customerNote;

    public function __construct(string $orderId, ?string $customerNote = null)
    {
        $this->orderId      = $orderId;
        $this->customerNote = $customerNote;
    }
}
