<?php

declare(strict_types=1);

namespace App\Paystack;

/**
 * Validates Paystack webhook requests.
 *
 * Paystack signs the raw request body with HMAC-SHA512 using your secret key and sends it
 * in the X-Paystack-Signature header.
 */
final class Webhook
{
    /** Paystack's published webhook source IPs (optional extra check). */
    public const PAYSTACK_IPS = ['52.31.139.75', '52.49.173.169', '52.214.14.220'];

    public function __construct(private string $secretKey)
    {
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secretKey);
    }

    public function isValid(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $payload === '') {
            return false;
        }
        return hash_equals($this->sign($payload), strtolower(trim($signature)));
    }

    /**
     * @return array<string,mixed>
     */
    public function parse(string $payload, ?string $signature): array
    {
        if (!$this->isValid($payload, $signature)) {
            throw new PaystackException('Invalid webhook signature');
        }
        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['event'])) {
            throw new PaystackException('Malformed webhook payload');
        }
        return $event;
    }

    public static function isFromPaystackIp(string $ip): bool
    {
        return in_array($ip, self::PAYSTACK_IPS, true);
    }
}
