<?php
// extensions/multi-pickup/src/Services/SmsService.php
// Sends SMS via Africa's Talking (works in UG, KE, NG, GH, TZ, ZM, RW)

namespace Extensions\MultiPickup\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $username;
    private string $apiKey;
    private string $baseUrl = 'https://api.africastalking.com/version1/messaging';

    public function __construct()
    {
        $this->username = env('AFRICASTALKING_USERNAME', 'sandbox');
        $this->apiKey   = env('AFRICASTALKING_API_KEY', '');

        // Use sandbox URL in development
        if ($this->username === 'sandbox') {
            $this->baseUrl = 'https://api.sandbox.africastalking.com/version1/messaging';
        }
    }

    /**
     * Send tracking link SMS to customer when order is dispatched.
     *
     * @param string $phone     Customer phone e.g. +256701234567
     * @param string $orderId   Fleetbase order UUID
     * @param string $displayId Human-readable order number e.g. "1042"
     * @param string $merchant  Merchant name
     */
    public function sendTrackingLink(
        string $phone,
        string $orderId,
        string $displayId,
        string $merchant
    ): bool {
        $trackingUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        $trackingUrl .= "/track?order_id={$orderId}&display_id={$displayId}&merchant=" . urlencode($merchant);

        $message = "Your order #{$displayId} from {$merchant} is on its way! 🛵\n"
                 . "Track your delivery and confirm when ready:\n"
                 . $trackingUrl;

        return $this->send($phone, $message);
    }

    /**
     * Send delivery confirmation SMS.
     */
    public function sendDeliveryConfirmed(
        string $phone,
        string $displayId,
        string $merchant
    ): bool {
        $message = "✅ Your order #{$displayId} from {$merchant} has been delivered!\n"
                 . "Thank you for your order.";

        return $this->send($phone, $message);
    }

    /**
     * Raw SMS send.
     */
    public function send(string $to, string $message): bool
    {
        if (!$this->apiKey) {
            Log::info("SMS (no key configured): TO={$to} | MSG={$message}");
            return true; // Silently skip in dev
        }

        try {
            $response = Http::withHeaders([
                'apiKey'       => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ])->asForm()->post($this->baseUrl, [
                'username' => $this->username,
                'to'       => $to,
                'message'  => $message,
            ]);

            $data = $response->json();
            $status = $data['SMSMessageData']['Recipients'][0]['status'] ?? 'unknown';

            Log::info("SMS sent to {$to}: {$status}");

            return $status === 'Success';
        } catch (\Exception $e) {
            Log::error("SMS failed to {$to}: " . $e->getMessage());
            return false;
        }
    }
}
