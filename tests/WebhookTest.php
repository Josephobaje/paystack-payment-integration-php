<?php

declare(strict_types=1);

namespace Tests;

use App\Paystack\PaystackException;
use App\Paystack\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'sk_test_webhooksecret';

    public function testValidSignatureMatchesPaystackAlgorithm(): void
    {
        $payload = '{"event":"charge.success","data":{"reference":"REF1","amount":500000}}';
        $signature = hash_hmac('sha512', $payload, self::SECRET); // what Paystack sends
        $webhook = new Webhook(self::SECRET);
        $this->assertTrue($webhook->isValid($payload, $signature));
        $this->assertTrue($webhook->isValid($payload, strtoupper($signature)));
        $this->assertSame('REF1', $webhook->parse($payload, $signature)['data']['reference']);
    }

    public function testTamperedPayloadOrWrongSecretFails(): void
    {
        $payload = '{"event":"charge.success","data":{"amount":500000}}';
        $sig = hash_hmac('sha512', $payload, self::SECRET);
        $webhook = new Webhook(self::SECRET);
        $this->assertFalse($webhook->isValid(str_replace('500000', '100', $payload), $sig));
        $this->assertFalse((new Webhook('sk_test_other'))->isValid($payload, $sig));
        $this->assertFalse($webhook->isValid($payload, null));
        $this->assertFalse($webhook->isValid('', $sig));
    }

    public function testParseRejectsInvalid(): void
    {
        $this->expectException(PaystackException::class);
        (new Webhook(self::SECRET))->parse('{"event":"x"}', 'deadbeef');
    }

    public function testParseRejectsMalformedJson(): void
    {
        $payload = 'not json';
        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('Malformed');
        (new Webhook(self::SECRET))->parse($payload, hash_hmac('sha512', $payload, self::SECRET));
    }

    public function testIpAllowList(): void
    {
        $this->assertTrue(Webhook::isFromPaystackIp('52.31.139.75'));
        $this->assertFalse(Webhook::isFromPaystackIp('1.2.3.4'));
    }
}
