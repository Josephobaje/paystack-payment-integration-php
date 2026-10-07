<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Paystack\PaystackException;

$back = static function (string $msg): never {
    header('Location: index.php?error=' . rawurlencode($msg));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $back('Invalid request');
}
if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    $back('Your session expired. Please try again.');
}
$product = PRODUCTS[$_POST['product'] ?? ''] ?? null;
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$product || !$email) {
    $back('Choose a product and enter a valid email.');
}
$payments = payments();
if (!$payments) {
    $back('Payments are not configured yet. Add PAYSTACK_SECRET_KEY to .env.');
}

try {
    // The amount comes from the server-side catalogue, never from the browser.
    $url = $payments->startCheckout($email, $product['name'], $product['price'], app_config()['app_url'] . '/callback.php');
    header('Location: ' . $url, true, 303);
} catch (PaystackException|InvalidArgumentException $e) {
    error_log('Paystack initialize failed: ' . $e->getMessage());
    $back('Could not start the payment: ' . $e->getMessage());
}
