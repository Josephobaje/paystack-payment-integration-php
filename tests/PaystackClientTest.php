<?php

declare(strict_types=1);

namespace Tests;

use App\Paystack\PaystackClient;
use App\Paystack\PaystackException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaystackClientTest extends TestCase
{
    private const KEY = 'sk_test_0123456789abcdef';

    public function testInitializeSendsCorrectRequest(): void
    {
        $http = (new FakeTransport())->queue(200, ['status' => true, 'message' => 'Authorization URL created', 'data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc', 'reference' => 'REF1',
        ]]);
        $client = new PaystackClient(self::KEY, $http);
        $data = $client->initialize('ada@example.com', 500000, 'REF1', 'https://shop.test/callback.php', ['product' => 'SEO']);

        $this->assertSame('https://checkout.paystack.com/abc', $data['authorization_url']);
        $req = $http->requests[0];
        $this->assertSame('POST', $req['method']);
        $this->assertSame('https://api.paystack.co/transaction/initialize', $req['url']);
        $this->assertSame('Bearer ' . self::KEY, $req['headers']['Authorization']);
        $body = json_decode($req['body'], true);
        $this->assertSame(['email' => 'ada@example.com', 'amount' => 500000, 'currency' => 'NGN', 'reference' => 'REF1',
            'callback_url' => 'https://shop.test/callback.php', 'metadata' => ['product' => 'SEO']], $body);
    }

    public function testVerifyUsesGetWithEncodedReference(): void
    {
        $http = (new FakeTransport())->queue(200, ['status' => true, 'data' => ['status' => 'success', 'amount' => 100]]);
        $data = (new PaystackClient(self::KEY, $http))->verify('JO_2026_abc');
        $this->assertSame('success', $data['status']);
        $this->assertSame('GET', $http->requests[0]['method']);
        $this->assertStringEndsWith('/transaction/verify/JO_2026_abc', $http->requests[0]['url']);
        $this->assertNull($http->requests[0]['body']);
    }

    public function testApiErrorsBecomeExceptions(): void
    {
        $http = (new FakeTransport())->queue(401, ['status' => false, 'message' => 'Invalid key']);
        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('Invalid key');
        (new PaystackClient(self::KEY, $http))->verify('REF');
    }

    public function testNonJsonResponse(): void
    {
        $this->expectException(PaystackException::class);
        (new PaystackClient(self::KEY, new FakeTransport()))->verify('REF');
    }

    public function testValidation(): void
    {
        $client = new PaystackClient(self::KEY, new FakeTransport());
        foreach ([fn () => $client->initialize('not-an-email', 1000, 'R', 'u'),
                  fn () => $client->initialize('a@b.co', 50, 'R', 'u'),
                  fn () => $client->verify('../../etc/passwd'),
                  fn () => new PaystackClient('pk_test_wrongkey')] as $call) {
            try {
                $call();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testReferenceAndMode(): void
    {
        $ref = PaystackClient::generateReference();
        $this->assertMatchesRegularExpression('/^JO_\d{14}_[a-f0-9]{12}$/', $ref);
        $this->assertNotSame($ref, PaystackClient::generateReference());
        $this->assertFalse((new PaystackClient(self::KEY))->isLiveMode());
        $this->assertTrue((new PaystackClient('sk_live_abc'))->isLiveMode());
    }
}
