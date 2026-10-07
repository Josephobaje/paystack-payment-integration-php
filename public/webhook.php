<?php

declare(strict_types=1);

/*
 * Paystack webhook endpoint. Set its URL in Paystack Dashboard → Settings → API Keys & Webhooks,
 * e.g. https://your-domain.com/webhook.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Paystack\PaystackException;
use App\Paystack\Webhook;
use App\Support\Env;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$secret = app_config()['secret'];
if (!$secret) {
    http_response_code(503);
    exit;
}

$webhook = new Webhook($secret);
$payload = (string) file_get_contents('php://input');

if (Env::get('PAYSTACK_ENFORCE_IP') === 'true' && !Webhook::isFromPaystackIp($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(403);
    exit;
}

try {
    $event = $webhook->parse($payload, $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? null);
} catch (PaystackException $e) {
    http_response_code(401);
    exit;
}

// Acknowledge quickly; Paystack retries non-200 responses.
http_response_code(200);

if ($event['event'] === 'charge.success') {
    $reference = (string) ($event['data']['reference'] ?? '');
    try {
        // Re-verify with the API rather than trusting the payload alone.
        payments()?->confirm($reference);
    } catch (Throwable $e) {
        error_log("Webhook confirm failed for $reference: " . $e->getMessage());
    }
}
