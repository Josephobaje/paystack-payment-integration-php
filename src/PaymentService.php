<?php

declare(strict_types=1);

namespace App;

use App\Orders\OrderRepository;
use App\Paystack\PaystackClient;
use RuntimeException;

/** Ties orders to Paystack: start checkout, then confirm payment from the callback or webhook. */
final class PaymentService
{
    public function __construct(private PaystackClient $paystack, private OrderRepository $orders)
    {
    }

    /** @return string the Paystack checkout URL to redirect the customer to */
    public function startCheckout(string $email, string $product, int $amountKobo, string $callbackUrl): string
    {
        $reference = PaystackClient::generateReference();
        $this->orders->create($reference, $email, $product, $amountKobo);
        $result = $this->paystack->initialize($email, $amountKobo, $reference, $callbackUrl, ['product' => $product]);
        return $result['authorization_url'];
    }

    /**
     * Confirm a payment with Paystack and fulfil the order exactly once.
     *
     * @return array{status:string, order:array<string,mixed>, newly_paid:bool}
     */
    public function confirm(string $reference): array
    {
        $order = $this->orders->find($reference);
        if ($order === null) {
            throw new RuntimeException('Unknown order reference');
        }
        if ($order['status'] === 'paid') {
            return ['status' => 'paid', 'order' => $order, 'newly_paid' => false];
        }

        $tx = $this->paystack->verify($reference);
        $ok = ($tx['status'] ?? '') === 'success'
            && (int) ($tx['amount'] ?? 0) === (int) $order['amount_kobo']   // guard against amount tampering
            && strtoupper((string) ($tx['currency'] ?? '')) === $order['currency'];

        if (!$ok) {
            $this->orders->markFailed($reference, (string) ($tx['gateway_response'] ?? $tx['status'] ?? 'verification failed'));
            return ['status' => 'failed', 'order' => $this->orders->find($reference), 'newly_paid' => false];
        }

        $newlyPaid = $this->orders->markPaid(
            $reference,
            (string) ($tx['channel'] ?? ''),
            (string) ($tx['gateway_response'] ?? ''),
            (string) ($tx['paid_at'] ?? date(DATE_ATOM))
        );
        // Deliver the product / send a receipt here when $newlyPaid is true.
        return ['status' => 'paid', 'order' => $this->orders->find($reference), 'newly_paid' => $newlyPaid];
    }
}
