<?php

declare(strict_types=1);

namespace App\Paystack;

use InvalidArgumentException;

/**
 * Minimal Paystack API client.
 *
 * @see https://paystack.com/docs/api/transaction/
 */
final class PaystackClient
{
    private const BASE_URL = 'https://api.paystack.co';

    private HttpTransport $http;

    public function __construct(private string $secretKey, ?HttpTransport $http = null)
    {
        if (!preg_match('/^sk_(test|live)_\w+$/', $secretKey)) {
            throw new InvalidArgumentException('PAYSTACK_SECRET_KEY must look like sk_test_... or sk_live_...');
        }
        $this->http = $http ?? new CurlTransport();
    }

    public function isLiveMode(): bool
    {
        return str_starts_with($this->secretKey, 'sk_live_');
    }

    /**
     * Start a transaction. Redirect the customer to the returned authorization_url.
     *
     * @param array<string,mixed> $metadata
     * @return array{authorization_url:string, access_code:string, reference:string}
     */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = [], string $currency = 'NGN'): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer email is required');
        }
        if ($amountKobo < 100) {
            throw new InvalidArgumentException('Amount must be at least 100 kobo');
        }
        return $this->call('POST', '/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Verify a transaction by reference. Always verify on the server before giving value.
     *
     * @return array<string,mixed>
     */
    public function verify(string $reference): array
    {
        if (!preg_match('/^[A-Za-z0-9._=-]{1,100}$/', $reference)) {
            throw new InvalidArgumentException('Invalid transaction reference');
        }
        return $this->call('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    public static function generateReference(string $prefix = 'JO'): string
    {
        return sprintf('%s_%s_%s', $prefix, date('YmdHis'), bin2hex(random_bytes(6)));
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    private function call(string $method, string $path, ?array $payload = null): array
    {
        $response = $this->http->request($method, self::BASE_URL . $path, [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Cache-Control' => 'no-cache',
        ], $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR));

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new PaystackException("Unexpected response from Paystack (HTTP {$response['status']})");
        }
        if ($response['status'] >= 400 || ($data['status'] ?? false) !== true) {
            throw new PaystackException($data['message'] ?? "Paystack request failed (HTTP {$response['status']})");
        }
        return $data['data'] ?? [];
    }
}
