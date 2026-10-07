<?php

declare(strict_types=1);

namespace Tests;

use App\Orders\OrderRepository;
use App\PaymentService;
use App\Paystack\PaystackClient;
use PHPUnit\Framework\TestCase;

final class PaymentServiceTest extends TestCase
{
    private FakeTransport $http;
    private OrderRepository $orders;
    private PaymentService $service;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->orders = OrderRepository::sqlite(':memory:');
        $this->service = new PaymentService(new PaystackClient('sk_test_abc123', $this->http), $this->orders);
    }

    private function checkout(int $amount = 2500000): string
    {
        $this->http->queue(200, ['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x', 'reference' => 'r']]);
        $url = $this->service->startCheckout('ada@example.com', 'SEO Audit', $amount, 'https://shop.test/callback.php');
        $this->assertSame('https://checkout.paystack.com/x', $url);
        return json_decode($this->http->requests[0]['body'], true)['reference'];
    }

    public function testCheckoutCreatesPendingOrder(): void
    {
        $ref = $this->checkout();
        $order = $this->orders->find($ref);
        $this->assertSame('pending', $order['status']);
        $this->assertSame(2500000, (int) $order['amount_kobo']);
    }

    public function testSuccessfulPaymentIsFulfilledOnce(): void
    {
        $ref = $this->checkout();
        $this->http->queue(200, ['status' => true, 'data' => ['status' => 'success', 'amount' => 2500000, 'currency' => 'NGN',
            'channel' => 'card', 'gateway_response' => 'Successful', 'paid_at' => '2026-10-07T10:00:00Z']]);
        $first = $this->service->confirm($ref);
        $this->assertSame('paid', $first['status']);
        $this->assertTrue($first['newly_paid']);
        $this->assertSame('card', $first['order']['channel']);

        // Callback and webhook can both arrive: the second confirm must not fulfil again or call the API.
        $second = $this->service->confirm($ref);
        $this->assertFalse($second['newly_paid']);
        $this->assertCount(2, $this->http->requests);
    }

    public function testAmountMismatchIsRejected(): void
    {
        $ref = $this->checkout(2500000);
        $this->http->queue(200, ['status' => true, 'data' => ['status' => 'success', 'amount' => 100, 'currency' => 'NGN']]);
        $this->assertSame('failed', $this->service->confirm($ref)['status']);
    }

    public function testAbandonedPaymentIsFailed(): void
    {
        $ref = $this->checkout();
        $this->http->queue(200, ['status' => true, 'data' => ['status' => 'abandoned', 'amount' => 2500000, 'currency' => 'NGN', 'gateway_response' => 'The transaction was not completed']]);
        $result = $this->service->confirm($ref);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('The transaction was not completed', $result['order']['gateway_response']);
    }

    public function testUnknownReference(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->confirm('NOPE');
    }
}
